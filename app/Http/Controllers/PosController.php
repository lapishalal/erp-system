<?php

namespace App\Http\Controllers;

use App\Models\CashIn;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PosTransaction;
use App\Models\PosTransactionDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDetail;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Services\JournalService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function getProducts(Request $request)
{
    $search = $request->get('search');
    $category = $request->get('category');

    $products = Product::with(['stock'])
        ->where('is_active', true)
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        })
        ->when($category && $category !== 'all', function ($query) use ($category) {
            $query->where('category_id', $category);
        })
        ->limit(20)
        ->get()
        ->map(function ($product) {
            return [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'price' => $product->default_sale_price,
                'stock' => $product->stock?->available_stock ?? 0,
                'image' => $product->avatar ?? null,
                'category_id' => $product->category_id, // <-- tambahkan ini
            ];
        });

    return response()->json($products);
}

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:CASH,DEBIT,QRIS,TRANSFER',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $items = $validated['items'];
        $discount = $validated['discount'] ?? 0;
        $tax = $validated['tax'] ?? 0;
        $paidAmount = $validated['paid_amount'];
        $paymentMethod = $validated['payment_method'];
        $customerId = $validated['customer_id'] ?? null;

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['qty'] * $item['price'];
        }

        $total = $subtotal - $discount + $tax;
        $changeAmount = $paidAmount - $total;

        if ($changeAmount < 0) {
            return response()->json(['success' => false, 'message' => 'Uang pembayaran kurang'], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Create POS Transaction
            $pos = PosTransaction::create([
                'transaction_number' => 'POS-' . date('Ymd') . '-' . rand(1000, 9999),
                'date' => now(),
                'customer_id' => $customerId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                PosTransactionDetail::create([
                    'pos_transaction_id' => $pos->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
            }

            // 2. Auto-create Sales Order (COMPLETE)
            $so = SalesOrder::create([
                'so_number' => 'SO-POS-' . $pos->transaction_number,
                'date' => now(),
                'customer_id' => $customerId ?? Customer::first()?->id,
                'status' => 'COMPLETE',
                'total_qty' => collect($items)->sum('qty'),
                'total_amount' => $total,
                'total_cost' => 0,
                'profit' => 0,
                'notes' => 'Auto-generated from POS: ' . $pos->transaction_number,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $costPrice = $product->last_buy_price ?? 0;

                SalesOrderDetail::create([
                    'so_id' => $so->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'unit_price' => $item['price'],
                    'cost_price' => $costPrice,
                    'delivered_qty' => $item['qty'],
                    'remaining_qty' => 0,
                    'subtotal' => $item['qty'] * $item['price'],
                    'profit' => ($item['price'] - $costPrice) * $item['qty'],
                ]);

                $so->total_cost += $costPrice * $item['qty'];
                $so->profit += ($item['price'] - $costPrice) * $item['qty'];
            }
            $so->save();

            // 3. Auto-create Delivery Order (DELIVERED)
            $do = DeliveryOrder::create([
                'do_number' => 'DO-POS-' . $pos->transaction_number,
                'so_id' => $so->id,
                'date' => now(),
                'customer_id' => $so->customer_id,
                'status' => 'DELIVERED',
                'total_qty' => $so->total_qty,
                'notes' => 'Auto-generated from POS',
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                DeliveryOrderDetail::create([
                    'do_id' => $do->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'notes' => 'POS transaction',
                ]);

                // Deduct stock
                $product = Product::find($item['product_id']);
                $costPrice = $product->last_buy_price ?? 0;
                StockService::deductStock(
                    $item['product_id'],
                    1,
                    $item['qty'],
                    $costPrice,
                    'OUT',
                    DeliveryOrder::class,
                    $do->id,
                    'POS ' . $pos->transaction_number,
                    auth()->id()
                );
            }

            // 4. Auto-create Sales Invoice (PAID)
            $invoice = SalesInvoice::create([
                'invoice_number' => 'INV-POS-' . $pos->transaction_number,
                'customer_id' => $so->customer_id,
                'so_id' => $so->id,
                'date' => now(),
                'due_date' => now(),
                'total' => $total,
                'paid_amount' => $paidAmount,
                'status' => 'PAID',
                'notes' => 'Auto-generated from POS',
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                SalesInvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
            }

            // 5. Auto-create Cash In
            $accountKas = \App\Models\Account::where('code', '1-10001')->first();
            $accountPiutang = \App\Models\Account::where('code', '1-10003')->first();

            CashIn::create([
                'account_id' => $accountKas?->id,
                'date' => now(),
                'type' => 'CUSTOMER_PAYMENT',
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
                'customer_id' => $so->customer_id,
                'amount' => $paidAmount,
                'description' => 'Pembayaran POS: ' . $pos->transaction_number,
                'created_by' => auth()->id(),
            ]);

            // 6. Auto Journal
            $totalHpp = 0;
            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $totalHpp += ($product->last_buy_price ?? 0) * $item['qty'];
            }

            JournalService::journalSalesInvoice($total, $totalHpp, auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'pos_id' => $pos->id,
                'transaction_number' => $pos->transaction_number,
                'total' => $total,
                'change' => $changeAmount,
                'message' => 'Transaksi berhasil',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function printReceipt($id)
    {
        $pos = PosTransaction::with(['details.product', 'customer'])->findOrFail($id);
        return view('pos.receipt', compact('pos'));
    }
}