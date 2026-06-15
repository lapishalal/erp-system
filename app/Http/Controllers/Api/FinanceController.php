<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashIn;
use App\Models\CashOut;
use App\Services\JournalService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function cashIn(Request $request)
    {
        $query = CashIn::with(['account', 'customer'])->orderBy('date', 'desc');

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function storeCashIn(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'type' => 'required|in:CUSTOMER_PAYMENT,OTHER_INCOME',
            'customer_id' => 'nullable|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable',
        ]);

        $cashIn = CashIn::create($validated + ['created_by' => auth()->id()]);

        return response()->json($cashIn, 201);
    }

    public function cashOut(Request $request)
    {
        $query = CashOut::with(['account', 'category'])->orderBy('date', 'desc');

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function storeCashOut(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'type' => 'required|in:OPERATIONAL,SALARY,TRANSPORT,MARKETING,UTILITIES,RENT,TAX,OTHER',
            'category_id' => 'nullable|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable',
        ]);

        $cashOut = CashOut::create($validated + ['created_by' => auth()->id()]);

        return response()->json($cashOut, 201);
    }
}