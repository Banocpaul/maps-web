<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediction_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name');
            $table->string('kind', 20);
            $table->unsignedSmallInteger('forecast_hours');
            $table->string('status', 20)->default('Running');
            $table->dateTime('requested_at');
            $table->dateTime('completed_at')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['requested_at', 'id']);
            $table->index(['forecast_hours', 'status']);
        });

        Schema::create('prediction_remarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prediction_execution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->string('barangay')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        DB::table('permissions')->updateOrInsert(['slug' => 'prediction.review'], [
            'name' => 'Review Prediction Results', 'module' => 'prediction',
            'description' => 'Add remarks to saved flood prediction runs.', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('slug', 'prediction.review')->value('id');
        foreach (DB::table('roles')->whereIn('slug', ['flood-analyst', 'operations-manager', 'operations-officer'])->pluck('id') as $roleId) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_remarks');
        Schema::dropIfExists('prediction_executions');
        // Keep role permissions to avoid removing pre-existing custom assignments.
    }
};
