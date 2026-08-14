<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOutlet;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToOutlet, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'outlet_id', 'invoice_number', 'shift_id', 'user_id', 'customer_id',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount',
        'tax_percent', 'tax_amount', 'service_charge_amount', 'rounding_amount',
        'total', 'paid_amount', 'change_amount', 'item_count', 'total_qty',
        'cost_total', 'profit', 'status', 'note',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'rounding_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'total_qty' => 'decimal:3',
            'cost_total' => 'decimal:2',
            'profit' => 'decimal:2',
            'item_count' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /** Sales inside an inclusive day range, expressed in the app timezone. */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [
            \Illuminate\Support\Carbon::parse($from)->startOfDay(),
            \Illuminate\Support\Carbon::parse($to)->endOfDay(),
        ]);
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /** Comma-separated tender labels, e.g. "Tunai, QRIS". */
    public function paymentLabel(): string
    {
        return $this->payments
            ->map(fn (SalePayment $payment) => $payment->methodLabel())
            ->unique()
            ->implode(', ');
    }
}
