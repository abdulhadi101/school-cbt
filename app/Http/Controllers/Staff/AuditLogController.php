<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('occurred_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $logs = $query->paginate(50)->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'auditable_type' => class_basename($log->auditable_type ?? ''),
            'auditable_id' => $log->auditable_id,
            'user_name' => $log->user?->name ?? 'System',
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'occurred_at' => $log->occurred_at?->toISOString(),
        ]);

        $actions = AuditLog::query()->distinct()->pluck('action')->sort()->values()->all();

        return Inertia::render('Staff/AuditLogs/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->only(['action', 'user_id', 'search']),
        ]);
    }
}
