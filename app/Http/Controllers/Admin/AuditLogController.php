<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workshop;
use App\Scopes\WorkshopScope;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $userId = $request->query('user_id');
        $action = $request->query('action');
        $subjectType = $request->query('subject_type');
        $from = $request->query('from');
        $to = $request->query('to');

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($q !== '', fn ($qb) => $qb->where('action', 'like', "%{$q}%"))
            ->when($userId, fn ($qb) => $qb->where('user_id', $userId))
            ->when($action, fn ($qb) => $qb->where('action', 'like', "{$action}%"))
            ->when($subjectType, fn ($qb) => $qb->where('subject_type', $subjectType))
            ->when($from, fn ($qb) => $qb->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to, fn ($qb) => $qb->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        $users = WorkshopScope::disabled(function () {
            return User::query()->orderBy('name')->get(['id', 'name']);
        });

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $subjectTypes = AuditLog::query()->distinct()->whereNotNull('subject_type')->orderBy('subject_type')->pluck('subject_type');

        return view('admin.audit-logs.index', [
            'title' => 'Audit logs',
            'logs' => $logs,
            'q' => $q,
            'users' => $users,
            'actions' => $actions,
            'subjectTypes' => $subjectTypes,
            'filters' => compact('userId', 'action', 'subjectType', 'from', 'to'),
        ]);
    }

    public function show(int $log): View
    {
        // Bypass the WorkshopScope global scope so any audit log row
        // can be inspected regardless of the viewing user's workshop.
        $log = WorkshopScope::disabled(fn () => AuditLog::query()->findOrFail($log));
        $log->load('user', 'workshop');

        return view('admin.audit-logs.show', [
            'title' => 'Audit log detail',
            'log' => $log,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->query('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->query('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($request->query('from'), fn ($q) => $q->where('created_at', '>=', $request->query('from').' 00:00:00'))
            ->when($request->query('to'), fn ($q) => $q->where('created_at', '<=', $request->query('to').' 23:59:59'))
            ->latest('created_at')
            ->limit(10000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'user', 'action', 'subject_type', 'subject_id', 'workshop_id', 'ip_address']);
            foreach ($query->lazy(500) as $row) {
                fputcsv($out, [
                    $row->created_at?->toDateTimeString(),
                    $row->user?->name ?? ($row->user_id ? "#{$row->user_id}" : '—'),
                    $row->action,
                    $row->subject_type,
                    $row->subject_id,
                    $row->workshop_id,
                    $row->ip_address,
                ]);
            }
            fclose($out);
        }, 'audit-logs-'.date('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
