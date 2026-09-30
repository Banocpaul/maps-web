<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_advisories', function (Blueprint $table): void {
            $table->id();

            /*
             * Keep the advisory even if the staff account is later removed.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('advisory_date')->index();

            /*
             * Flexible category values such as:
             * flood, fire, weather, evacuation,
             * class-suspension, and general.
             */
            $table->string('type', 50)->default('general')->index();

            $table->string('subject', 255);
            $table->text('message');

            /*
             * Optional image path. The storage disk will be handled
             * by the advisory controller/config in the next step.
             */
            $table->string('photo_path')->nullable();

            $table->timestamps();

            $table->index(
                ['advisory_date', 'created_at'],
                'public_advisories_newest_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_advisories');
    }
};
