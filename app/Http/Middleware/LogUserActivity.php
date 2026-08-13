<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogUserActivity
{
    private const SENSITIVE_FIELDS = [
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        'api_key',
        'secret',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $userBefore = $request->user();
        $response = $next($request);

        if (! $this->shouldLog($request)) {
            return $response;
        }

        try {
            if (! Schema::hasTable('activity_logs')) {
                return $response;
            }

            $user = $userBefore ?? $request->user();
            $routeName = $request->route()?->getName();
            $action = $this->resolveAction($request, $user);
            $module = $this->resolveModule($routeName);
            [$subjectType, $subjectId] = $this->resolveSubject($request);

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->full_name ?? $user?->name,
                'role_name' => $user?->role?->name,
                'action' => $action,
                'module' => $module,
                'description' => $this->description(
                    $user,
                    $action,
                    $module
                ),
                'route_name' => $routeName,
                'http_method' => $request->method(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'new_values' => $this->safeInput($request),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr(
                    (string) $request->userAgent(),
                    0,
                    1000
                ),
                'response_status' => $response->getStatusCode(),
            ]);
        } catch (Throwable) {
            // Activity logging must never interrupt an operational request.
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return false;
        }

        return ! $request->routeIs('activity-logs.*');
    }

    private function resolveAction(
        Request $request,
        ?User $user
    ): string {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            $routeName === 'login.attempt' && $user !== null => 'login',
            $routeName === 'login.attempt' => 'failed_login',
            $routeName === 'logout' => 'logout',
            str_contains($routeName, 'prediction.citywide') => 'run_prediction',
            str_contains($routeName, 'prediction.run') => 'run_prediction',
            str_contains($routeName, 'sms.send') => 'send_sms',
            str_contains($routeName, 'sms.test') => 'test_sms',
            str_contains($routeName, 'export') => 'export',
            $request->isMethod('DELETE') => 'delete',
            $request->isMethod('PUT') => 'update',
            $request->isMethod('PATCH') => 'update',
            $request->isMethod('POST') => 'create',
            default => strtolower($request->method()),
        };
    }

    private function resolveModule(?string $routeName): string
    {
        $prefix = explode('.', (string) $routeName)[0] ?? 'system';

        return match ($prefix) {
            'login', 'logout' => 'authentication',
            'fire-incidents' => 'fire-incidents',
            'fire-hydrants' => 'fire-hydrants',
            'flood-operation', 'flood-dataset' => 'flood',
            'prediction' => 'prediction',
            'gis' => 'gis',
            'sms' => 'sms',
            'users' => 'user-management',
            'reports' => 'reports',
            default => $prefix !== '' ? $prefix : 'system',
        };
    }

    private function resolveSubject(Request $request): array
    {
        foreach (($request->route()?->parameters() ?? []) as $parameter) {
            if ($parameter instanceof Model) {
                return [get_class($parameter), (int) $parameter->getKey()];
            }
        }

        return [null, null];
    }

    private function safeInput(Request $request): ?array
    {
        $input = $request->except(self::SENSITIVE_FIELDS);

        foreach ($input as $key => $value) {
            if (preg_match('/password|token|secret|credential|api.?key/i', $key)) {
                unset($input[$key]);
            }
        }

        return $input === [] ? null : $input;
    }

    private function description(
        ?User $user,
        string $action,
        string $module
    ): string {
        $name = $user?->full_name ?? $user?->name ?? 'Guest user';
        $readableAction = str_replace('_', ' ', $action);
        $readableModule = str_replace('-', ' ', $module);

        return "{$name} performed {$readableAction} in {$readableModule}.";
    }
}
