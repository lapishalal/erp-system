<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class JournalEntry extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'date',
        'reference_type',
        'reference_id',
        'description',
        'total_debit',
        'total_credit',
        'is_posted',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class, 'journal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}