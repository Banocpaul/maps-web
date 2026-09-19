<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->isAdministrator(),
            403,
            'Only administrators may access activity logs.'
        );

        $query = ActivityLog::query()->with('user.role');

        $this->applyFilters($query, $request);

        $logs = $query
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'users' => User::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'name', 'first_name', 'last_name']),
            'modules' => ActivityLog::query()
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'totalLogs' => ActivityLog::count(),
            'todayLogs' => ActivityLog::whereDate(
                'created_at',
                today('Asia/Manila')
            )->count(),
            'failedLogins' => ActivityLog::where(
                'action',
                'failed_login'
            )->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(
            $request->user()?->isAdministrator(),
            403,
            'Only administrators may export activity logs.'
        );

        $query = ActivityLog::query()->latest('created_at');
        $this->applyFilters($query, $request);

        $filename = 'maps-audit-trail-'.now('Asia/Manila')->format('Y-m-d-His').'.xls';

        return response()->streamDownload(function () use ($query): void {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"><style>';
            echo 'table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:11pt}';
            echo 'th{background:#0f172a;color:#fff;font-weight:bold}';
            echo 'th,td{border:1px solid #cbd5e1;padding:6px;vertical-align:top}';
            echo '.text{mso-number-format:"\\@"}';
            echo '</style></head><body><table><thead><tr>';

            foreach ([
                'Date and Time (Asia/Manila)', 'User', 'Role', 'Action',
                'Module', 'Description', 'Route', 'HTTP Method',
                'Subject Type', 'Subject ID', 'Old Values', 'New Values',
                'IP Address', 'User Agent', 'Response Status',
            ] as $heading) {
                echo '<th>'.e($heading).'</th>';
            }

            echo '</tr></thead><tbody>';

            foreach ($query->cursor() as $log) {
                $values = [
                    $log->created_at?->timezone('Asia/Manila')->format('Y-m-d h:i:s A'),
                    $log->user_name ?? 'Guest user',
                    $log->role_name ?? 'No role',
                    $log->action,
                    $log->module,
                    $log->description,
                    $log->route_name,
                    $log->http_method,
                    $log->subject_type,
                    $log->subject_id,
                    $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    $log->ip_address,
                    $log->user_agent,
                    $log->response_status,
                ];

                echo '<tr>';

                foreach ($values as $value) {
                    $safeValue = (string) ($value ?? '');

                    if ($safeValue !== '' && in_array($safeValue[0], ['=', '+', '-', '@'], true)) {
                        $safeValue = "'".$safeValue;
                    }

                    echo '<td class="text">'.e($safeValue).'</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function applyFilters(
        Builder $query,
        Request $request
    ): void {
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());

            $query->where(function (Builder $subQuery) use ($search): void {
                $subQuery
                    ->where('user_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
    }
}
