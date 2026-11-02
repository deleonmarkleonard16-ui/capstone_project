<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditLogController extends Controller
{
    private const HIDDEN_BACKGROUND_ROUTES = [
        'answers.save-progress',
        'guidance.progress',
        'api.notifications.clear-module',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:admin,staff'],
            'status' => ['nullable', 'in:success,denied,failed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'export' => ['nullable', 'in:csv'],
        ]);

        $query = $this->filteredQuery($filters);

        if (($filters['export'] ?? null) === 'csv') {
            return $this->exportCsv($query);
        }

        return view('admin.audit-logs', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = AuditLog::query()
            ->with('user:id,name')
            ->whereNotIn('route', self::HIDDEN_BACKGROUND_ROUTES)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('user_name', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('route', 'like', "%{$search}%"));
        }
        if (! empty($filters['role'])) $query->where('user_role', $filters['role']);
        if (! empty($filters['status'])) {
            $query->where(function (Builder $query) use ($filters): void {
                match ($filters['status']) {
                    'success' => $query->where('response_status', '<', 400),
                    'denied' => $query->whereIn('response_status', [401, 403]),
                    'failed' => $query->where('response_status', '>=', 400)->whereNotIn('response_status', [401, 403]),
                };
            });
        }
        if (! empty($filters['from'])) $query->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to'])) $query->whereDate('created_at', '<=', $filters['to']);

        return $query;
    }

    private function exportCsv(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'User', 'Role', 'Activity', 'Route', 'Method', 'Date and Time', 'IP Address', 'Status', 'Details']);

            $query->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, [
                        'LOG-'.str_pad((string) $log->id, 4, '0', STR_PAD_LEFT),
                        $log->user?->name ?? $log->user_name ?? 'Deleted user',
                        ucfirst((string) $log->user_role),
                        $log->activityDescription(),
                        $log->route,
                        $log->method,
                        $log->created_at?->format('Y-m-d h:i:s A'),
                        $log->ip_address,
                        $log->response_status < 400 ? 'Success' : (in_array($log->response_status, [401, 403], true) ? 'Denied' : 'Failed'),
                        $log->subject ? collect($log->subject)->map(fn ($value, $key) => "$key: $value")->join('; ') : '',
                    ]);
                }
            });

            fclose($output);
        }, 'audit-logs-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
