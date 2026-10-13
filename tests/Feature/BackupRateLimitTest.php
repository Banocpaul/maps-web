<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BackupRateLimitTest extends TestCase
{
    public function test_backup_actions_use_independent_counters_without_running_a_restore(): void
    {
        foreach (['store' => '3,10', 'verify' => '5,10', 'download' => '5,10', 'restore' => '1,10', 'destroy' => '3,10'] as $action => $limit) {
            Route::middleware(['web', 'throttle:'.$limit.',backup-'.$action.'-'])
                ->post('/test-backup-limits/'.$action, fn () => response('', 204))
                ->name('admin.backups.test-'.$action);
        }
        $this->post('/test-backup-limits/store')->assertNoContent();
        $this->post('/test-backup-limits/verify')->assertNoContent();
        $this->post('/test-backup-limits/download')->assertNoContent();
        $this->post('/test-backup-limits/restore')->assertNoContent();
        $this->post('/test-backup-limits/restore')->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('backup_cooldown');
        $this->post('/test-backup-limits/verify')->assertNoContent();
    }
}
