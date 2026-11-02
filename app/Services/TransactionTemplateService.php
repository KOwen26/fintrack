<?php

namespace App\Services;

use App\Enums\Category;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reference-data CRUD + read projections for user-private transaction
 * templates. Static reads and instance writes mirror AccountService. No
 * events, no caching — personal, small, low-frequency data.
 */
final readonly class TransactionTemplateService
{
    /** Management list: newest first, accounts eager-loaded for display. */
    public static function getTemplatesByUser(User $user): Collection
    {
        return TransactionTemplate::query()
            ->where('user_id', $user->id)
            ->with(['account:id,name', 'destinationAccount:id,name'])
            ->latest('id')
            ->get();
    }

    /**
     * Owner-scoped lookup for form seeding. The returned instance is a
     * pruned read projection: unselectable defaults are nulled so the
     * folding DTO never resurrects an archived/deleted account.
     */
    public static function getTemplateById(User $user, int $templateId): ?TransactionTemplate
    {
        $template = TransactionTemplate::query()
            ->where('user_id', $user->id)
            ->find($templateId);

        return $template !== null ? self::prune(self::selectableAccountIds($user), $template) : null;
    }

    /**
     * The user's templates as in-memory read projections for the create
     * form's template-select. Mutates unsaved instances only.
     */
    public static function getQuickAccessTemplates(User $user): Collection
    {
        $selectableIds = self::selectableAccountIds($user);

        return self::getTemplatesByUser($user)
            ->map(fn (TransactionTemplate $template): TransactionTemplate => self::prune($selectableIds, $template));
    }

    public function create(User $creator, array $data): TransactionTemplate
    {
        return TransactionTemplate::create([...$data, 'user_id' => $creator->id]);
    }

    public function update(TransactionTemplate $template, array $data): TransactionTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function delete(TransactionTemplate $template): void
    {
        $template->delete();
    }

    /** Owned + not archived + not soft-deleted account ids. */
    private static function selectableAccountIds(User $user): Collection
    {
        return AccountService::getAccountIdsByUser($user);
    }

    /**
     * Null out defaults that are no longer selectable, and guard category
     * drift (a stored slug removed from the enum in future).
     */
    private static function prune(Collection $selectableIds, TransactionTemplate $template): TransactionTemplate
    {
        if ($template->account_id !== null && ! $selectableIds->contains($template->account_id)) {
            $template->setAttribute('account_id', null);
        }

        if ($template->destination_account_id !== null && ! $selectableIds->contains($template->destination_account_id)) {
            $template->setAttribute('destination_account_id', null);
        }

        $rawCategory = $template->getRawOriginal('category_id');

        if (is_string($rawCategory) && Category::tryFrom($rawCategory) === null) {
            $template->setAttribute('category_id', null);
        }

        return $template;
    }
}
