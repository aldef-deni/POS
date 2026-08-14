<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class ActivityLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'guard', 'action', 'subject_type', 'subject_id',
        'description', 'properties', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an auditable action. Never throws — an audit failure must not
     * be allowed to roll back the business action it was describing.
     */
    public static function record(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
        ?string $guard = null,
    ): void {
        try {
            $user ??= auth('web')->user() ?? auth('pos')->user();

            static::create([
                'tenant_id' => $user?->tenant_id ?? app(\App\Support\Tenancy::class)->id(),
                'user_id' => $user?->id,
                'guard' => $guard ?? (auth('web')->check() ? 'web' : (auth('pos')->check() ? 'pos' : null)),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 191),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
