<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('flood_observation_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flood_training_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->string('action', 30);
            $table->string('previous_code', 1)->nullable();
            $table->string('new_code', 1);
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->timestamp('observed_at');
            $table->text('note')->nullable();
            $table->json('notification_summary')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('flood_observation_changes'); }
};
