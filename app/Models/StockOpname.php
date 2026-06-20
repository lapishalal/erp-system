<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class StockOpname extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'warehouse_id',
        'opname_date',
        'status',
        'notes',
        'created_by',
        'approved_by',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $opname) {
            if ($opname->status === 'COMPLETED') {
                $opname->load('details');
                foreach ($opname->details as $detail) {
                    self::syncStock($detail, $opname->warehouse_id);
                    self::createJournal($detail, $opname->warehouse_id, 'OPNAME');
                }
            }
        });

        static::deleting(function (self $opname) {
            if ($opname->status === 'COMPLETED') {
                $opname->load('details');
                foreach ($opname->details as $detail) {
                    self::rollbackStock($detail, $opname->warehouse_id);
                    self::createJournal($detail, $opname->warehouse_id, 'OPNAME_ROLLBACK');
                }
            }
        });
    }

    public static function syncStock(StockOpnameDetail $detail, int $warehouseId): void
    {
        $stock = \App\Models\ProductStock::firstOrCreate(
            [
                'product_id' => $detail->product_id,
                'warehouse_id' => $warehouseId,
            ],
            [
                'physical_stock' => 0,
                'outstanding_stock' => 0,
                'available_stock' => 0,
            ]
        );

        $stock->physical_stock = $detail->physical_qty;
        $stock->available_stock = max(0, $stock->physical_stock - $stock->outstanding_stock);
        $stock->save();
    }

    public static function rollbackStock(StockOpnameDetail $detail, int $warehouseId): void
    {
        $stock = \App\Models\ProductStock::where('product_id', $detail->product_id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock) {
            $stock->physical_stock = $detail->system_qty;
            $stock->available_stock = max(0, $stock->physical_stock - $stock->outstanding_stock);
            $stock->save();
        }
    }

    protected static function createJournal(StockOpnameDetail $detail, int $warehouseId, string $type): void
    {
        $persediaanId = self::getAccountIdByCode('1-20001');
        $hppId = self::getAccountIdByCode('5-10001');
        $pendapatanLainId = self::getAccountIdByCode('4-20001');

        if (!$persediaanId || !$hppId || !$pendapatanLainId) return;

        $selisih = $detail->physical_qty - $detail->system_qty;
        if ($selisih == 0) return;

        $product = $detail->product;
        $harga = $product?->last_buy_price ?? 0;
        $nilai = abs($selisih) * $harga;

        if ($nilai <= 0) return;

        $journal = \App\Models\JournalEntry::create([
            'date' => now(),
            'reference_type' => self::class,
            'reference_id' => $detail->opname_id,
            'description' => 'Stock Opname: ' . $product?->name . ' (' . $type . ') Selisih: ' . $selisih,
            'total_debit' => $nilai,
            'total_credit' => $nilai,
            'is_posted' => true,
            'created_by' => auth()->id(),
        ]);

        if ($selisih < 0) {
            // Minus: HPP (D) vs Persediaan (K)
            $journal->details()->create([
                'account_id' => $hppId,
                'debit' => $nilai,
                'credit' => 0,
                'description' => 'Kerugian stok opname (minus)',
            ]);
            $journal->details()->create([
                'account_id' => $persediaanId,
                'debit' => 0,
                'credit' => $nilai,
                'description' => 'Pengurangan persediaan',
            ]);
        } else {
            // Plus: Persediaan (D) vs Pendapatan Lain (K)
            $journal->details()->create([
                'account_id' => $persediaanId,
                'debit' => $nilai,
                'credit' => 0,
                'description' => 'Penambahan persediaan',
            ]);
            $journal->details()->create([
                'account_id' => $pendapatanLainId,
                'debit' => 0,
                'credit' => $nilai,
                'description' => 'Keuntungan stok opname (plus)',
            ]);
        }
    }

    protected static function getAccountIdByCode(string $code): ?int
    {
        $account = \App\Models\Account::where('code', $code)->first();
        return $account?->id;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOpnameDetail::class, 'opname_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}