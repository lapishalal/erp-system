<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class CashIn extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $table = 'cash_in';

    protected $fillable = [
        'account_id',
        'date',
        'type',
        'reference_type',
        'reference_id',
        'customer_id',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // ✅ Auto Jurnal saat CashIn tercreate
        static::created(function (self $cashIn) {
            self::createJournal($cashIn);
        });

        // ✅ Reverse jurnal saat CashIn dihapus
        static::deleted(function (self $cashIn) {
            \App\Models\JournalEntry::where('reference_type', self::class)
                ->where('reference_id', $cashIn->id)
                ->delete();
        });
    }

    protected static function createJournal(self $cashIn): void
    {
        $account = $cashIn->account;
        if (!$account) return;

        // Tentukan akun kredit berdasarkan type
        $creditAccountId = match ($cashIn->type) {
            'CUSTOMER_PAYMENT' => self::getAccountIdByCode('4-10001'), // Penjualan
            'OTHER_INCOME' => self::getAccountIdByCode('4-20001'), // Pendapatan Lain
            default => null,
        };

        if (!$creditAccountId) return;

        $journal = \App\Models\JournalEntry::create([
            'date' => $cashIn->date,
            'reference_type' => self::class,
            'reference_id' => $cashIn->id,
            'description' => $cashIn->description ?? 'Cash In: ' . $cashIn->type,
            'total_debit' => $cashIn->amount,
            'total_credit' => $cashIn->amount,
            'is_posted' => true,
            'created_by' => $cashIn->created_by,
        ]);

        // Debit: Kas/Bank
        $journal->details()->create([
            'account_id' => $cashIn->account_id,
            'debit' => $cashIn->amount,
            'credit' => 0,
            'description' => 'Kas/Bank masuk',
        ]);

        // Kredit: Penjualan / Pendapatan Lain
        $journal->details()->create([
            'account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $cashIn->amount,
            'description' => $cashIn->type === 'CUSTOMER_PAYMENT' ? 'Pendapatan penjualan' : 'Pendapatan lain-lain',
        ]);

        // Update invoice paid_amount kalau CUSTOMER_PAYMENT
        if ($cashIn->type === 'CUSTOMER_PAYMENT' && $cashIn->reference_type && $cashIn->reference_id) {
            $invoice = \App\Models\SalesInvoice::find($cashIn->reference_id);
            if ($invoice) {
                $invoice->paid_amount += $cashIn->amount;
                if ($invoice->paid_amount >= $invoice->total) {
                    $invoice->status = 'PAID';
                } elseif ($invoice->paid_amount > 0) {
                    $invoice->status = 'PARTIAL';
                }
                $invoice->save();
            }
        }
    }

    protected static function getAccountIdByCode(string $code): ?int
    {
        $account = \App\Models\Account::where('code', $code)->first();
        return $account?->id;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}