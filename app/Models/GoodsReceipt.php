<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        // ============================================
        // FIX: Auto jurnal saat GR dibuat langsung RECEIVED
        // Hapus DB::afterCommit() — tidak jalan tanpa transaction
        // Langsung panggil createJournal (akan return karena details belum ada, tapi aman)
        // Jurnal sebenarnya dibuat oleh GoodsReceiptDetail::saved()
        // ============================================
        static::created(function (self $gr) {
            if ($gr->status === 'RECEIVED') {
                self::createJournal($gr);
            }
        });

        // ============================================
        // FIX: Auto jurnal saat status berubah ke RECEIVED
        // Refresh instance agar dapat data terbaru dari DB
        // ============================================
        static::updated(function (self $gr) {
            if ($gr->isDirty('status') && $gr->status === 'RECEIVED') {
                $gr->refresh();
                self::createJournal($gr);
            }
        });

        // ============================================
        // Hapus jurnal + restore PO detail saat GR dihapus
        // ============================================
        static::deleting(function (self $gr) {
            // Hapus jurnal terkait jika status RECEIVED
            if ($gr->status === 'RECEIVED') {
                \App\Models\JournalEntry::where('reference_type', self::class)
                    ->where('reference_id', $gr->id)
                    ->delete();
            }

            // Restore PO detail qty
            if ($gr->po_id) {
                $gr->load('details');
                foreach ($gr->details as $detail) {
                    self::restorePurchaseOrderDetail($detail, $gr->po_id);
                }
            }
        });
    }

    // ============================================
    // FIX: Ubah dari protected static jadi public static
    // Agar bisa dipanggil dari GoodsReceiptDetail
    // ============================================
    public static function createJournal(self $gr): void
    {
        // 1. Cek duplikasi: jika jurnal untuk GR ini sudah ada, skip
        $existing = \App\Models\JournalEntry::where('reference_type', self::class)
            ->where('reference_id', $gr->id)
            ->first();

        if ($existing) {
            return;
        }

        // 2. Ambil akun
        $persediaanId = self::getAccountIdByCode('1-20001'); // Persediaan Barang Dagang
        $hutangId = self::getAccountIdByCode('2-10001'); // Hutang Usaha

        if (!$persediaanId || !$hutangId) {
            return;
        }

        // ============================================
        // FIX: Hitung total dari DB query langsung (paling akurat)
        // ============================================
        $totalAmount = (float) DB::table('goods_receipt_details')
            ->where('gr_id', $gr->id)
            ->sum(DB::raw('qty * buy_price'));

        // Fallback: jika query DB return 0, coba dari relasi details
        if ($totalAmount <= 0) {
            $gr->loadMissing('details');
            $totalAmount = (float) $gr->details->sum(function ($detail) {
                return $detail->qty * $detail->buy_price;
            });
        }

        // Fallback ke kolom total_amount di DB
        if ($totalAmount <= 0) {
            $totalAmount = (float) DB::table('goods_receipts')
                ->where('id', $gr->id)
                ->value('total_amount');
        }

        // Safety: jika total masih 0, tidak buat jurnal
        if ($totalAmount <= 0) {
            return;
        }

        // 3. Buat jurnal header
        $journal = \App\Models\JournalEntry::create([
            'date' => $gr->date,
            'reference_type' => self::class,
            'reference_id' => $gr->id,
            'description' => 'Penerimaan barang: ' . $gr->gr_number,
            'total_debit' => $totalAmount,
            'total_credit' => $totalAmount,
            'is_posted' => true,
            'created_by' => $gr->created_by,
        ]);

        // 4. Debit: Persediaan
        $journal->details()->create([
            'account_id' => $persediaanId,
            'debit' => $totalAmount,
            'credit' => 0,
            'description' => 'Persediaan masuk dari GR ' . $gr->gr_number,
        ]);

        // 5. Kredit: Hutang Usaha
        $journal->details()->create([
            'account_id' => $hutangId,
            'debit' => 0,
            'credit' => $totalAmount,
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