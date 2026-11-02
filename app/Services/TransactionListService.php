<?php

namespace App\Services;

use App\Data\Transaction\TransactionListCursor;
use App\Data\Transaction\TransactionListData;
use App\Data\Transaction\TransactionListFilters;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionSort;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Objects\DatePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Everything that reads the transaction list's truth — the global window,
 * keyset pages, the summary, and the query vocabulary they share
 * (ownership/filter/period WHERE, keyset ordering, table-state filters).
 * Writes live in TransactionService.
 */
final class TransactionListService
{
    /** Rows fetched per "load more" request from the transaction index. */
    public const int PAGE_SIZE = 60;

    /**
     * Cache TTL for the full-window list. Writes flush eagerly via
     * FlushTransactionListCache, so this bounds only backstop staleness
     * (direct DB writes) and time-relative period rollover.
     */
    private const int CACHE_TTL_MINUTES = 15;

    /**
     * Cache key holding one user's cached full-window lists — a map keyed by
     * period preset, so a write flushes with a single exact Cache::forget.
     * The v1 segment orphans entries shaped by an older row mapper on deploy.
     */
    public static function cacheKey(int $userId): string
    {
        return "transaction-list:user:{$userId}";
    }

    /**
     * Global list: one row per transfer unit (the outflow row) plus plain
     * rows and fee rows — inflow rows are hidden.
     */
    public static function getTransactions(User $user, DatePeriodPreset $period): array
    {
        $transactions = self::listQuery($user, $period->toPeriod())->get();

        return TransactionListData::collectFromTransactions($transactions);
    }

    /**
     * Account variant of getTransactions — one account's rows as list data.
     * Deliberately not scoped to a creator: an account's list is the
     * account's truth, including rows other users created on shared
     * accounts (matching TransactionService::getAccountTransactions).
     */
    public static function getTransactionsByAccount(Account $account, DatePeriodPreset $period): array
    {
        $transactions = self::accountListQuery($account, $period->toPeriod())->get();

        return TransactionListData::collectFromTransactions($transactions);
    }

    /**
     * Cached variant of getTransactions — the transaction index read path.
     * Fills one period at a time into the per-user map; each fill refreshes
     * the map's TTL, which bounds only backstop staleness (direct DB writes)
     * since writes flush eagerly via FlushTransactionListCache. A concurrent
     * fill of another period can lose a race — self-healing, it recomputes.
     */
    public static function getCachedTransactions(User $user, DatePeriodPreset $period): array
    {
        $store = Cache::get(self::cacheKey($user->id), []);

        if (array_key_exists($period->value, $store)) {
            return $store[$period->value];
        }

        $store[$period->value] = self::getTransactions($user, $period);

        Cache::put(self::cacheKey($user->id), $store, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $store[$period->value];
    }

    /**
     * One keyset page of the global list — filtered and ordered per `filters`,
     * capped at PAGE_SIZE after the cursor, with the paging meta the client
     * must pass through (total matching rows in the period, whether further
     * rows exist past the fetched page, and the opaque cursor to continue).
     *
     * @return array{transactions: list<array<string, mixed>>, meta: array{total: int, has_more: bool, next_cursor: string|null}}
     */
    public static function getTransactionsByCursorPage(
        User $user,
        ?TransactionListCursor $cursor,
        DatePeriodPreset $period,
        ?TransactionListFilters $filters = null,
    ): array {
        $filters ??= new TransactionListFilters;

        $countQuery = self::baseQuery($user, $period->toPeriod());
        self::applyListFilters($countQuery, $filters);
        $total = (int) $countQuery->count();

        $list = self::loadedQuery($user, $period->toPeriod());
        self::applyListFilters($list, $filters);
        self::applySort($list, $filters->sort, $cursor);

        $transactions = $list->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $transactions->count() > self::PAGE_SIZE;

        $nextCursor = null;

        if ($hasMore) {
            $last = $transactions[self::PAGE_SIZE - 1];
            $nextCursor = TransactionListCursor::toWire(
                $filters->sort,
                self::cursorValue($last, $filters->sort),
                $last->id,
            );
        }

        return [
            'transactions' => TransactionListData::collectFromTransactions($transactions->take(self::PAGE_SIZE)),
            'meta' => [
                'total' => $total,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    public static function getSummary(User $user, DatePeriodPreset $period): array
    {
        $row = self::baseQuery($user, $period->toPeriod())
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expense',
                [TransactionType::Income->value, TransactionType::Expense->value]
            )
            ->first();

        $income = (float) ($row->income ?? 0);
        $expense = (float) ($row->expense ?? 0);

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    /** Narrows a list query in place by the request's search text and ids. */
    private static function applyListFilters(Builder $list, TransactionListFilters $filters): void
    {
        // An empty id array must stay unfiltered — a whereIn([]) would match
        // nothing.
        if ($filters->accountIds !== []) {
            $list->whereIn('account_id', $filters->accountIds);
        }

        if ($filters->categoryIds !== []) {
            $list->whereIn('category_id', $filters->categoryIds);
        }

        if ($filters->types !== []) {
            $list->whereIn('type', $filters->types);
        }

        // OR-match: a row carrying any selected tag qualifies. JSON columns
        // take no index; the window is already user + period bounded.
        if ($filters->tags !== []) {
            $list->whereRaw('JSON_OVERLAPS(tags, ?)', [json_encode($filters->tags)]);
        }

        $search = $filters->search;

        if ($search === null || $search === '') {
            return;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search) . '%';

        // Category names live on a PHP enum, so name matches resolve to
        // candidate ids here rather than in SQL.
        $categoryIds = array_map(
            fn (Category $category): string => $category->value,
            array_values(array_filter(
                Category::cases(),
                fn (Category $category): bool => mb_stripos($category->label(), $search) !== false,
            )),
        );

        $list->where(function (Builder $query) use ($like, $categoryIds): void {
            $query->where('description', 'like', $like)
                ->orWhereRaw('CAST(amount AS CHAR) like ?', [$like])
                ->orWhereHas('account', fn (Builder $account) => $account->where('name', 'like', $like))
                ->orWhereHas(
                    'transfer.destinationTransaction.account',
                    fn (Builder $account) => $account->where('name', 'like', $like),
                )
                // Stored form is JSON text (["food", ...]) — a plain substring match; multi-word terms won't match underscored slugs.
                ->orWhereRaw('tags like ?', [$like]);

            if ($categoryIds !== []) {
                $query->orWhereIn('category_id', $categoryIds);
            }
        });
    }

    /** Orders by the requested sort and narrows to rows past the cursor. */
    private static function applySort(Builder $list, TransactionSort $sort, ?TransactionListCursor $cursor): void
    {
        $column = $sort->column();
        $direction = $sort->direction();

        $list->orderBy($column, $direction)->orderBy('id', $direction);

        if ($cursor === null) {
            return;
        }

        $operator = $direction === 'desc' ? '<' : '>';

        $list->where(function (Builder $query) use ($column, $cursor, $operator): void {
            $query->where($column, $operator, $cursor->value)
                ->orWhere(function (Builder $query) use ($column, $cursor, $operator): void {
                    $query->where($column, $cursor->value)
                        ->where('id', $operator, $cursor->id);
                });
        });
    }

    /** The wire value of a row's keyset column, in SQL-comparable form. */
    private static function cursorValue(Transaction $transaction, TransactionSort $sort): string
    {
        if ($sort->cursorPrefix() === 'amount') {
            return (string) $transaction->getRawOriginal('amount');
        }

        return $transaction->transaction_date->format('Y-m-d H:i:s');
    }

    /** The list's ownership, type and period filters; count targets use this alone. */
    private static function baseQuery(User $user, DatePeriod $period): Builder
    {
        return Transaction::query()
            ->where('created_by', $user->id)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period);
    }

    /**
     * The fully-ordered, eagerly-loaded list query. Keyset tiebreaker on `id`
     * makes the newest-first order deterministic per (date, id) — the unit of
     * the cursor.
     */
    private static function listQuery(User $user, DatePeriod $period): Builder
    {
        return self::loadedQuery($user, $period)
            ->latest('transaction_date')
            ->orderByDesc('id');
    }

    /**
     * The account view's list query — like listQuery but narrowed to the
     * account's rows with no creator scope (shared-account truth); transfer
     * inflow rows stay hidden, an outgoing transfer appears once with its
     * destination.
     */
    private static function accountListQuery(Account $account, DatePeriod $period): Builder
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period)
            ->with([
                'account:id,name',
                'transfer.destinationTransaction.account:id,name',
            ])
            ->latest('transaction_date')
            ->orderByDesc('id');
    }

    /** Ownership/type/period filters plus the eager loads list rows need. */
    private static function loadedQuery(User $user, DatePeriod $period): Builder
    {
        return self::baseQuery($user, $period)
            ->with([
                'account:id,name',
                'transfer.destinationTransaction.account:id,name',
            ]);
    }
}
