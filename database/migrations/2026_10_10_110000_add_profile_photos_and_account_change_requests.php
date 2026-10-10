<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_change_requests', function (Blueprint $table) {
            $table->string('current_email')->nullable();
            $table->string('requested_email')->nullable();
            $table->boolean('changes_password')->default(true);
        });
        Schema::create('profile_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mime_type');
            $table->longText('image_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_photos');
        Schema::table('password_change_requests', fn (Blueprint $table) => $table->dropColumn(['current_email', 'requested_email', 'changes_password']));
    }
};
