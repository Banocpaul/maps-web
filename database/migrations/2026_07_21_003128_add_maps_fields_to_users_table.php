<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('id')
                ->constrained('roles')
                ->nullOnDelete();

            $table->string('first_name')->after('role_id');

            $table->string('last_name')->after('first_name');

            $table->string('contact_number', 30)
                ->nullable()
                ->after('email');

            $table->boolean('is_active')
                ->default(true)
                ->after('password');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->timestamp('approved_at')
                ->nullable()
                ->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);

            $table->dropColumn([
                'role_id',
                'first_name',
                'last_name',
                'contact_number',
                'is_active',
                'last_login_at',
                'approved_at',
            ]);
        });
    }
};