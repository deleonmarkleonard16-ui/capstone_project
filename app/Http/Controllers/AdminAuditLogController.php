<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,staff'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('user_name', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('route', 'like', "%{$search}%"));
        }
        if (! empty($filters['role'])) $query->where('user_role', $filters['role']);
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);

        return view('admin.audit-logs', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => $filters,
        ]);
    }
}
