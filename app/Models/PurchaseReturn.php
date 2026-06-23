<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class PurchaseReturn extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'return_number',
        'supplier_id',
        'reference_gr_id',
        'date',
        'status',
        'total_qty',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_qty' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $return) {
            if ($return->status === 'PROCESSED') {
                throw new \Exception('Retur yang sudah diproses tidak boleh dihapus. Gunakan Cancel untuk membatalkan.');
            }
        });
    }

    public static function processReturn(int $returnId): void
    {
        DB::transaction(function () use ($returnId) {
            // Lock row
            DB::table('purchase_returns')
                ->where('id', $returnId)
                ->lockForUpdate()
                ->first();

            // Cek duplikasi jurnal
            if (DB::table('journal_entries')
                ->where('reference_type', self::class)
                ->where('reference_id', $returnId)
                ->exists()) {
                return;
            }

            $returnRow = DB::table('purchase_returns')->where('id', $returnId)->first();
            if (!$returnRow || $returnRow->status !== 'PROCESSED') {
                return;
            }

            $details = DB::table('purchase_return_details')
                ->where('return_id', $returnId)
                ->get();

            if ($details->isEmpty()) {
                return;
            }

            $hutangId = self::getAccountIdByCode('2-10001');
            $persediaanId = self::getAccountIdByCode('1-20001');

            if (!$hutangId || !$persediaanId) {
                throw new \Exception('Akun Hutang Dagang (2-10001) atau Persediaan (1-20001) tidak ditemukan.');
            }

            $totalAmount = (float) $returnRow->total_amount;

            if ($totalAmount <= 0) {
                return;
            }

            $gr = DB::table('goods_receipts')->where('id', $returnRow->reference_gr_id)->first();
            $warehouseId = $gr?->warehouse_id ?? 1;

            foreach ($details as $detail) {
                if ($detail->qty <= 0) continue;

                $stockMovementExists = DB::table('stock_movements')
                    ->where('reference_type', self::class)
                    ->where('reference_id', $returnId)
                    ->where('product_id', $detail->product_id)
                    ->exists();

                if ($stockMovementExists) {
                    continue;
                }

                $stock = ProductStock::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                $availableStock = $stock?->available_stock ?? 0;

                if ($availableStock < $detail->qty) {
                    throw new \Exception(
                        "Stok tidak cukup untuk retur produk. Tersedia: {$availableStock}, Diminta retur: {$detail->qty}"
                    );
                }

                if ($stock) {
                    $qtyBefore = $stock->physical_stock;
                    $stock->physical_stock = max(0, $stock->physical_stock - $detail->qty);
                    $stock->available_stock = max(0, $stock->physical_stock - $stock->outstanding_stock);
                    $stock->save();

                    StockMovement::create([
                        'product_id' => $detail->product_id,
                        'warehouse_id' => $warehouseId,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $stock->physical_stock,
                        'delta' => -$detail->qty,
                        'type' => 'RETURN',
                        'reference_type' => self::class,
                        'reference_id' => $returnId,
                        'notes' => 'Retur Pembelian #' . $returnRow->return_number,
                    ]);

                    StockTransaction::create([
                        'product_id' => $detail->product_id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'OUT',
                        'reference_type' => self::class,
                        'reference_id' => $returnId,
                        'qty' => $detail->qty,
                        'price' => $detail->price,
                        'remaining_stock' => $stock->physical_stock,
                        'notes' => 'Retur #' . $returnRow->return_number . ' | Supplier: ' . ($returnRow->supplier_id ?? '-'),
                        'created_by' => $returnRow->created_by,
                    ]);
                }
            }

            $journal = JournalEntry::create([
                'date' => $returnRow->date,
                'reference_type' => self::class,
                'reference_id' => $returnId,
                'description' => 'Retur pembelian: ' . $returnRow->return_number,
                'total_debit' => $totalAmount,
                'total_credit' => $totalAmount,
                'is_posted' => true,
                'created_by' => $returnRow->created_by,
            ]);

            $journal->details()->create([
                'account_id' => $hutangId,
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => 'Pengurangan hutang supplier dari retur ' . $returnRow->return_number,
            ]);

            $journal->details()->create([
                'account_id' => $persediaanId,
                'debit' => 0,
                'credit' => $totalAmount,
                'description' => 'Pengurangan persediaan dari retur ' . $returnRow->return_number,
            ]);
        });
    }

    public static function cancelReturn(int $returnId): void
    {
        DB::transaction(function () use ($returnId) {
            DB::table('purchase_returns')
                ->where('id', $returnId)
                ->lockForUpdate()
                ->first();

            $returnRow = DB::table('purchase_returns')->where('id', $returnId)->first();
            if (!$returnRow || $returnRow->status !== 'CANCEL') {
                return;
            }

            DB::table('journal_entry_details')
                ->whereIn('journal_id', function ($query) use ($returnId) {
                    $query->select('id')
                        ->from('journal_entries')
                        ->where('reference_type', self::class)
                        ->where('reference_id', $returnId);
                })
                ->delete();

            DB::table('journal_entries')
                ->where('reference_type', self::class)
                ->where('reference_id', $returnId)
                ->delete();

            $details = DB::table('purchase_return_details')
                ->where('return_id', $returnId)
                ->get();

            $gr = DB::table('goods_receipts')->where('id', $returnRow->reference_gr_id)->first();
            $warehouseId = $gr?->warehouse_id ?? 1;

            foreach ($details as $detail) {
                if ($detail->qty <= 0) continue;

                $stock = ProductStock::where('product_id', $detail->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    $qtyBefore = $stock->physical_stock;
                    $stock->physical_stock += $detail->qty;
                    $stock->available_stock = max(0, $stock->physical_stock - $stock->outstanding_stock);
                    $stock->save();

                    StockMovement::create([
                        'product_id' => $detail->product_id,
                        'warehouse_id' => $warehouseId,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $stock->physical_stock,
                        'delta' => $detail->qty,
                        'type' => 'RETURN_CANCEL',
                        'reference_type' => self::class,
                        'reference_id' => $returnId,
                        'notes' => 'Pembatalan retur pembelian #' . $returnRow->return_number,
                    ]);
                }
            }
        });
    }

    protected static function getAccountIdByCode(string $code): ?int
    {
        $account = Account::where('code', $code)->first();
        return $account?->id;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function referenceGr(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'reference_gr_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}