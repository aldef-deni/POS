<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'opened_at', 'closed_at',
        'opening_cash', 'cash_sales', 'non_cash_sales', 'expected_cash',
        'counted_cash', 'cash_variance', 'total_sales', 'total_transactions',
        'total_refunds', 'status', 'opening_note', 'closing_note', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'non_cash_sales' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_refunds' => 'decimal:2',
            'total_transactions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Cash that should be in the drawer right now. */
    public function expectedCashNow(): float
    {
        return (float) $this->opening_cash + (float) $this->cash_sales;
    }

    public function durationLabel(): string
    {
        $end = $this->closed_at ?? now();
        $minutes = $this->opened_at->diffInMinutes($end);

        return sprintf('%dj %dm', intdiv($minutes, 60), $minutes % 60);
    }
}
