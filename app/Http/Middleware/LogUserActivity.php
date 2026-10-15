<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogUserActivity
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $userBefore = $request->user();
        [$subjectType, $subjectId, $oldValues] =
            $this->resolveSubjectBeforeAction($request);

        try {
            $response = $next($request);
        } catch (Throwable $error) {
            app(\App\Services\Audit\AuditRecorder::class)->flushInserts();
            $status = $error instanceof \Illuminate\Validation\ValidationException ? 422
                : ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $error->getStatusCode() : ($error instanceof \Illuminate\Auth\Access\AuthorizationException ? 403
                        : ($error instanceof \Illuminate\Auth\AuthenticationException ? 401
                            : ($error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500))));
            $this->recordRequest($request, $userBefore, $subjectType, $subjectId, $oldValues, $status, true);
            throw $error;
        }
        app(\App\Services\Audit\AuditRecorder::class)->flushInserts();
        $failed = $response->getStatusCode() >= 400
            || (! $request->isMethodSafe() && $request->hasSession()
                && in_array('errors', $request->session()->get('_flash.new', []), true));
        $this->recordRequest($request, $userBefore, $subjectType, $subjectId, $oldValues,
            $response->getStatusCode(), $failed);
        return $response;
    }

    public function recordFailure(Request $request, int $status): void
    {
        if (! $request->attributes->get('_audit_recorded')) {
            $this->recordRequest($request, $request->user(), null, null, null, $status, true);
        }
    }

    private function recordRequest(Request $request, ?User $userBefore, ?string $subjectType,
        ?int $subjectId, ?array $oldValues, int $status, bool $failed): void
    {
        if ($request->attributes->get('_audit_recorded')) {
            return;
        }
        $request->attributes->set('_audit_recorded', true);
        try {
            $user = $request->routeIs('login.attempt', 'public.login.attempt') ? $request->user()
                : ($userBefore ?? $request->user());
            $routeName = $request->route()?->getName();
            $action = $this->resolveAction($request, $failed ? null : $user);
            if ($failed && $action !== 'failed_login') {
                $action = 'failed_'.$action;
            }
            $module = $this->resolveModule($routeName);
            $input = $this->safeInput($request) ?? [];
            $input['_audit'] = ['path' => $request->route()?->uri() ?? '[unmatched route]', 'outcome' => $failed ? 'Failed' : 'Completed'];
            foreach ($request->allFiles() as $key => $file) {
                // File contents and temporary paths must never enter the trail.
                $files = is_array($file) ? \Illuminate\Support\Arr::flatten($file) : [$file];
                foreach ($files as $upload) {
                    if ($upload instanceof \Illuminate\Http\UploadedFile) {
                        $input['_audit']['uploads'][$key][] = ['extension' => $upload->getClientOriginalExtension(),
                            'size_bytes' => $upload->getSize()];
                    }
                }
            }
            app(\App\Services\Audit\AuditRecorder::class)->record([
                'user_id' => $user?->id, 'user_name' => $this->userName($user),
                'role_name' => $this->roleName($user),
                'action' => $action, 'module' => $module,
                'description' => $this->description($user, $action, $module, $subjectType, $subjectId)
                    .' Outcome: '.($failed ? 'Failed' : 'Completed').'.',
                'route_name' => $routeName, 'http_method' => $request->method(),
                'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'old_values' => $oldValues, 'new_values' => $input,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'response_status' => $status,
            ]);
        } catch (Throwable $error) {
            app(\App\Services\Audit\AuditRecorder::class)->flushInserts();
            \Illuminate\Support\Facades\Log::warning('Request audit could not be recorded.', ['error_type' => get_class($error)]);
        }
    }

    private function resolveAction(
        Request $request,
        ?User $user
    ): string {
        $routeName = (string) $request->route()?->getName();

        return match (true) {
            $request->routeIs('login.attempt', 'public.login.attempt') && $user !== null =>
                'login',

            $request->routeIs('login.attempt', 'public.login.attempt') =>
                'failed_login',

            $routeName === 'logout' =>
                'logout',

            $routeName === 'fire-incidents.fire-out' => 'record_fire_out',
            $routeName === 'fire-incidents.finalize' => 'finalize_fire_record',

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

            $routeName === 'admin.backups.store' =>
                'create_backup',

            $routeName === 'admin.backups.verify' =>
                'verify_backup',

            $routeName === 'admin.backups.download' =>
                'download_backup',

            $routeName === 'admin.backups.restore' =>
                'restore_backup',

            $routeName === 'admin.backups.destroy' =>
                'delete_backup',

            $request->isMethod('DELETE') =>
                'delete',

            $request->isMethod('PUT'),
            $request->isMethod('PATCH') =>
                'update',

            $request->isMethod('POST') && str_contains($routeName, '.') =>
                str_replace('-', '_', substr($routeName, strrpos($routeName, '.') + 1)),
            $request->isMethod('POST') => 'create',

            $request->isMethod('GET') && str_contains($routeName, 'download') => 'download',
            $request->isMethod('GET') && $request->filled('search') => 'search',
            $request->isMethod('GET') => 'view',
            default => strtolower($request->method()),
        };
    }

    private function resolveModule(?string $routeName): string
    {
        $prefix = explode('.', (string) $routeName)[0] ?? 'system';

        return match ($prefix) {
            'login', 'logout' => 'authentication',
            'public' => str_contains((string) $routeName, 'login') ? 'authentication' : 'public-portal',
            'public-submissions' => 'incident-reports',
            'fire-incidents' => 'fire-incidents',
            'fire-hydrants' => 'fire-hydrants',
            'flood-operation', 'flood-dataset' => 'flood',
            'prediction' => 'prediction',
            'gis' => 'gis',
            'sms', 'sms-center' => 'sms',
            'users' => 'user-management',
            'reports' => 'reports',
            'operational-records' => 'operational-records',
            'admin' => str_contains((string) $routeName, 'backups')
                ? 'backup-recovery'
                : 'administration',
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
                ! $request->isMethodSafe()
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
        return app(\App\Services\Audit\AuditRecorder::class)->redact($model->attributesToArray());
    }

    private function safeInput(Request $request): ?array
    {
        return app(\App\Services\Audit\AuditRecorder::class)->redact($request->input());
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
