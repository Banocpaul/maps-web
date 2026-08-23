<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        'remember_token',
        'token',
        'api_key',
        'secret',
        'credential',
    ];

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $userBefore = $request->user();
        [$subjectType, $subjectId, $oldValues] =
            $this->resolveSubjectBeforeAction($request);

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

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $this->userName($user),
                'role_name' => $this->roleName($user),
                'action' => $action,
                'module' => $module,
                'description' => $this->description(
                    $user,
                    $action,
                    $module,
                    $subjectType,
                    $subjectId
                ),
                'route_name' => $routeName,
                'http_method' => $request->method(),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'old_values' => $oldValues,
                'new_values' => $this->safeInput($request),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr(
                    (string) $request->userAgent(),
                    0,
                    1000
                ),
                'response_status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Activity log could not be recorded.', [
                'route_name' => $request->route()?->getName(),
                'http_method' => $request->method(),
                'user_id' => $userBefore?->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return false;
        }

        if ($request->isMethod('GET')) {
            return $request->routeIs('*.export');
        }

        return ! $request->routeIs('activity-logs.*');
    }

    private function resolveAction(
        Request $request,
        ?User $user
    ): string {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            $routeName === 'login.attempt' && $user !== null =>
                'login',

            $routeName === 'login.attempt' =>
                'failed_login',

            $routeName === 'logout' =>
                'logout',

            str_contains($routeName, 'prediction.citywide'),
            str_contains($routeName, 'prediction.run') =>
                'run_prediction',

            str_contains($routeName, 'sms.send') =>
                'send_sms',

            str_contains($routeName, 'sms.test') =>
                'test_sms',

            str_contains($routeName, 'training-status') =>
                'change_training_status',

            str_contains($routeName, '.status') =>
                'change_status',

            str_contains($routeName, 'reset-password') =>
                'reset_password',

            str_contains($routeName, 'export') =>
                'export',

            $request->isMethod('DELETE') =>
                'delete',

            $request->isMethod('PUT'),
            $request->isMethod('PATCH') =>
                'update',

            $request->isMethod('POST') =>
                'create',

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
            'sms', 'sms-center' => 'sms',
            'users' => 'user-management',
            'reports' => 'reports',
            'operational-records' => 'operational-records',
            default => $prefix !== '' ? $prefix : 'system',
        };
    }

    private function resolveSubjectBeforeAction(
        Request $request
    ): array {
        foreach (($request->route()?->parameters() ?? []) as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            $oldValues = null;

            if (
                $request->isMethod('PUT') ||
                $request->isMethod('PATCH') ||
                $request->isMethod('DELETE')
            ) {
                $oldValues = $this->safeModelAttributes(
                    $parameter
                );
            }

            return [
                get_class($parameter),
                (int) $parameter->getKey(),
                $oldValues,
            ];
        }

        return [null, null, null];
    }

    private function safeModelAttributes(Model $model): ?array
    {
        $attributes = $model->attributesToArray();

        foreach (array_keys($attributes) as $key) {
            if ($this->isSensitiveKey((string) $key)) {
                unset($attributes[$key]);
            }
        }

        return $attributes === [] ? null : $attributes;
    }

    private function safeInput(Request $request): ?array
    {
        $input = $request->except(self::SENSITIVE_FIELDS);

        foreach (array_keys($input) as $key) {
            if ($this->isSensitiveKey((string) $key)) {
                unset($input[$key]);
            }
        }

        return $input === [] ? null : $input;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match(
            '/password|token|secret|credential|api.?key/i',
            $key
        );
    }

    private function userName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return $user->full_name !== ''
            ? $user->full_name
            : $user->name;
    }

    private function roleName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        // Query the relationship directly because the users table still has
        // a legacy "role" string column with the same name.
        return $user->role()->value('name');
    }

    private function description(
        ?User $user,
        string $action,
        string $module,
        ?string $subjectType,
        ?int $subjectId
    ): string {
        $name = $this->userName($user) ?? 'Guest user';
        $readableAction = str_replace('_', ' ', $action);
        $readableModule = str_replace('-', ' ', $module);

        $subject = $subjectType !== null && $subjectId !== null
            ? ' Record #' . $subjectId . ' was affected.'
            : '';

        return "{$name} performed {$readableAction} " .
            "in {$readableModule}.{$subject}";
    }
}
