<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $emptyPaginator = fn () => new LengthAwarePaginator([], 0, 25, (int) $request->input('page', 1), [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if (! Schema::hasTable('audit_logs')) {
            return view('admin.audit-logs.index', [
                'logs' => $emptyPaginator(),
                'filterActions' => collect(),
                'filterTypes' => collect(),
            ]);
        }

        $query = AuditLog::query()->with('user');

        $userTerm = $request->string('user')->trim()->value();
        if ($userTerm !== '') {
            if (strcasecmp($userTerm, 'system') === 0) {
                $query->whereNull('user_id');
            } else {
                $like = '%'.addcslashes($userTerm, '%_\\').'%';
                $query->where(function ($q) use ($like) {
                    $q->whereHas('user', function ($uq) use ($like) {
                        $uq->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                });
            }
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $auditableType = $request->input('record_type', $request->input('auditable_type'));
        if (filled($auditableType)) {
            $query->where('auditable_type', $auditableType);
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (int) $request->input('auditable_id'));
        }

        if ($request->filled('ip_address')) {
            $ip = addcslashes($request->string('ip_address')->trim(), '%_\\');
            $query->where('ip_address', 'like', '%'.$ip.'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $filterActions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $filterTypes = AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type');

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filterActions' => $filterActions,
            'filterTypes' => $filterTypes,
        ]);
    }
}
