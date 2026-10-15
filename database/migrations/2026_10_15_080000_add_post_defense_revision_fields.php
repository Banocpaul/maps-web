<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        DB::table('incident_report_settings')->where('id', 1)->update(['threshold' => 5]);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('residence_type', 40)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
        });
        Schema::table('public_incident_reports', function (Blueprint $table): void {
            $table->string('street')->nullable();
            $table->string('street_key')->nullable()->index();
            $table->boolean('confirmed_fraud')->default(false);
            $table->string('alarm_level', 40)->nullable();
            $table->string('alarm_source')->nullable();
            $table->unsignedInteger('injured_count')->nullable();
            $table->unsignedInteger('deceased_count')->nullable();
        });
        foreach (['fire_incidents', 'flood_training_records', 'flood_incident_records'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedInteger('injured_count')->nullable();
                $table->unsignedInteger('deceased_count')->nullable();
            });
        }
        Schema::create('fire_incident_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fire_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('observed_at');
            $table->string('status', 30);
            $table->string('alarm_level', 40)->nullable();
            $table->string('alarm_source')->nullable();
            $table->text('notes');
            $table->timestamps();
        });
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('public_report_group_id')->nullable();
            $table->unique(['public_report_group_id', 'sms_recipient_id', 'alert_key'], 'community_alert_recipient_unique');
        });
        Schema::create('report_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('dataset');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('report_archives');
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropUnique('community_alert_recipient_unique');
            $table->dropColumn('public_report_group_id');
        });
        Schema::dropIfExists('fire_incident_updates');
        foreach (['fire_incidents', 'flood_training_records', 'flood_incident_records'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['injured_count', 'deceased_count']));
        }
        Schema::table('public_incident_reports', fn (Blueprint $table) => $table->dropColumn([
            'street', 'street_key', 'confirmed_fraud', 'alarm_level', 'alarm_source', 'injured_count', 'deceased_count',
        ]));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['residence_type', 'suspended_at', 'suspension_reason']));
    }
};
