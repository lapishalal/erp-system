<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class GoodsReceipt extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $fillable = [
        'gr_number',
        'po_id',
        'supplier_id',
        'warehouse_id',
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
        // ✅ Auto Jurnal saat status berubah ke RECEIVED
        static::updated(function (self $gr) {
            if ($gr->isDirty('status') && $gr->status === 'RECEIVED') {
                self::createJournal($gr);
            }
        });

        // ✅ Hapus jurnal saat GR dihapus
        static::deleting(function (self $gr) {
            if ($gr->status === 'RECEIVED') {
                \App\Models\JournalEntry::where('reference_type', self::class)
                    ->where('reference_id', $gr->id)
                    ->delete();
            }
        });

        // ✅ Restore PO detail (sama seperti sebelumnya)
        static::deleting(function (self $gr) {
            if ($gr->po_id) {
                $gr->load('details');
                foreach ($gr->details as $detail) {
                    self::restorePurchaseOrderDetail($detail, $gr->po_id);
                }
            }
        });
    }

    protected static function createJournal(self $gr): void
    {
        $persediaanId = self::getAccountIdByCode('1-20001'); // Persediaan Barang Dagang
        $hutangId = self::getAccountIdByCode('2-10001'); // Hutang Usaha

        if (!$persediaanId || !$hutangId) return;

        $journal = \App\Models\JournalEntry::create([
            'date' => $gr->date,
            'reference_type' => self::class,
            'reference_id' => $gr->id,
            'description' => 'Penerimaan barang: ' . $gr->gr_number,
            'total_debit' => $gr->total_amount,
            'total_credit' => $gr->total_amount,
            'is_posted' => true,
            'created_by' => $gr->created_by,
        ]);

        // Debit: Persediaan
        $journal->details()->create([
            'account_id' => $persediaanId,
            'debit' => $gr->total_amount,
            'credit' => 0,
            'description' => 'Persediaan masuk dari GR ' . $gr->gr_number,
        ]);

        // Kredit: Hutang Usaha
        $journal->details()->create([
            'account_id' => $hutangId,
            'debit' => 0,
            'credit' => $gr->total_amount,
            'description' => 'Hutang supplier dari GR ' . $gr->gr_number,
        ]);
    }

    protected static function restorePurchaseOrderDetail(GoodsReceiptDetail $detail, int $poId): void
    {
        $poDetail = \App\Models\PurchaseOrderDetail::where('po_id', $poId)
            ->where('product_id', $detail->product_id)
            ->first();

        if (!$poDetail) return;

        $poDetail->received_qty = max(0, ($poDetail->received_qty ?? 0) - $detail->qty);
        $poDetail->remaining_qty = max(0, $poDetail->qty - $poDetail->received_qty);
        $poDetail->save();

        $po = \App\Models\PurchaseOrder::with('details')->find($poId);
        if ($po) {
            $totalRemaining = $po->details->sum('remaining_qty');
            $totalQty = $po->details->sum('qty');
            $totalReceived = $po->details->sum('received_qty');

            if ($totalReceived == 0) {
                $po->status = 'DRAFT';
            } elseif ($totalRemaining > 0) {
                $po->status = 'PARTIAL';
            } else {
                $po->status = 'COMPLETE';
            }
            $po->save();
        }
    }

    protected static function getAccountIdByCode(string $code): ?int
    {
        $account = \App\Models\Account::where('code', $code)->first();
        return $account?->id;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(GoodsReceiptDetail::class, 'gr_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}