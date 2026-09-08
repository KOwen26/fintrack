<?php

namespace App\Http\Controllers;

use App\Data\DataTablePayloadData;
use App\Helpers\DataTableHelper;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DevController extends Controller
{
    public function tableV9(): Response
    {
        $transactions = Transaction::query()
            ->select(['id', 'transaction_date', 'description', 'amount', 'type'])
            ->latest('transaction_date')
            ->limit(50)
            ->get();

        return Inertia::render('dev/table-v9', [
            'transactions' => $transactions,
        ]);
    }

    public function tableV9Server(Request $request): JsonResponse
    {
        $payload = DataTablePayloadData::fromQueryParams($request->query());

        $query = Transaction::query()->select(['id', 'transaction_date', 'description', 'amount', 'type']);

        return response()->json(DataTableHelper::parse($query, $payload));
    }
}
