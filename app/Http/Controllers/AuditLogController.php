<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\InertiaLinks;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

/**
 * Read-only view of the audit trail.
 *
 * Raw audit information remains stored in the database for forensic
 * purposes, but the UI receives only human-readable presentation data.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'action',
            'subject',
            'user_id',
            'q',
            'from',
            'to',
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when(
                $filters['action'] ?? null,
                fn ($query, $value) => $query->where('action', $value),
            )
            ->when(
                $filters['subject'] ?? null,
                fn ($query, $value) => $query->where('auditable_type', $value),
            )
            ->when(
                $filters['user_id'] ?? null,
                fn ($query, $value) => $query->where('user_id', $value),
            )
            ->when(
                $filters['from'] ?? null,
                fn ($query, $value) => $query->whereDate(
                    'created_at',
                    '>=',
                    $value,
                ),
            )
            ->when(
                $filters['to'] ?? null,
                fn ($query, $value) => $query->whereDate(
                    'created_at',
                    '<=',
                    $value,
                ),
            )
            ->when($filters['q'] ?? null, function ($query) use ($filters): void {
                $term = trim((string) $filters['q']);

                $query->where(function ($inner) use ($term): void {
                    $inner
                        ->when(
                            is_numeric($term),
                            fn ($q) => $q->where(
                                'auditable_id',
                                (int) $term,
                            ),
                        )
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereJsonContains('old_values', $term)
                        ->orWhereJsonContains('new_values', $term);
                });
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('audit-log/index', [
            'logs' => $logs
                ->through(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'subject' => $log->subjectLabel(),
                    'user' => $log->user
                        ? [
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ]
                        : null,
                    'ip_address' => $log->ip_address,
                    'changes' => $log->changes(),
                ])
                ->all(),

            'pagination' => InertiaLinks::from($logs),

            'filters' => [
                'action' => $filters['action'] ?? '',
                'subject' => $filters['subject'] ?? '',
                'user_id' => isset($filters['user_id'])
                    ? (int) $filters['user_id']
                    : null,
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'q' => $filters['q'] ?? '',
            ],

            'subjects' => collect(
                AuditLog::query()
                    ->distinct()
                    ->pluck('auditable_type'),
            )
                ->filter()
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => match (class_basename($type)) {
                        'JournalEntry' => 'Journal Entry',
                        'JournalEntryLine' => 'Journal Entry Line',
                        'PosEntry' => 'POS Entry',
                        'Company' => 'Company',
                        'Branch' => 'Branch',
                        'Account' => 'Account',
                        'User' => 'User',
                        'FiscalYear' => 'Fiscal Year',
                        'AccountingPeriod' => 'Accounting Period',
                        default => Str::headline(class_basename($type)),
                    },
                ])
                ->sortBy('label')
                ->values(),

            'actions' => AuditLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),

            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),

            'stats' => [
                'total' => AuditLog::query()->count(),
                'today' => AuditLog::query()
                    ->whereDate('created_at', now()->toDateString())
                    ->count(),
            ],
        ]);
    }
}