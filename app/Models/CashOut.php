<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;

class CashOut extends Model
{
    use HasFactory, Auditable, BelongsToTenant;

    protected $table = 'cash_out';

    protected $fillable = [
        'account_id',
        'date',
        'type',
        'amount',
        'category_id',
        'description',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (self $cashOut) {
            self::createJournal($cashOut);
        });

        static::deleted(function (self $cashOut) {
            \App\Models\JournalEntry::where('reference_type', self::class)
                ->where('reference_id', $cashOut->id)
                ->delete();
        });
    }

    protected static function createJournal(self $cashOut): void
    {
        $category = $cashOut->category;
        if (!$category || !$category->account_id) return;

        $journal = \App\Models\JournalEntry::create([
            'date' => $cashOut->date,
            'reference_type' => self::class,
            'reference_id' => $cashOut->id,
            'description' => $cashOut->description ?? 'Cash Out: ' . $category->name,
            'total_debit' => $cashOut->amount,
            'total_credit' => $cashOut->amount,
            'is_posted' => true,
            'created_by' => $cashOut->created_by,
        ]);

        // Debit: Beban (sesuai kategori)
        $journal->details()->create([
            'account_id' => $category->account_id,
            'debit' => $cashOut->amount,
            'credit' => 0,
            'description' => 'Beban: ' . $category->name,
        ]);

        // Kredit: Kas/Bank
        $journal->details()->create([
            'account_id' => $cashOut->account_id,
            'debit' => 0,
            'credit' => $cashOut->amount,
            'description' => 'Kas/Bank keluar',
        ]);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}