<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Columns that should never be copied into the audit trail.
     *
     * @var list<string>
     */
    protected array $except = [
        'created_at',
        'updated_at',
        'password',
        'remember_token',
    ];

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(string $action, Model $subject, array $oldValues, array $newValues): void
    {
        $request = request();

        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $this->normalise($oldValues),
            'new_values' => $this->normalise($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
        ]);
    }

    /**
     * Keep the payload flat and JSON safe.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalise(array $values): array
    {
        $values = array_diff_key($values, array_flip($this->except));

        return array_map(
            fn (mixed $value): mixed => match (true) {
                is_scalar($value) || $value === null => $value,
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => json_encode($value),
            },
            $values,
        );
    }
}
