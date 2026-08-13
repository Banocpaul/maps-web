<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
