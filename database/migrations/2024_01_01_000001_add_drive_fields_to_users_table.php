<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])->default('user')->after('email');
            $table->bigInteger('storage_quota')->default(104857600)->after('role'); // 100MB default
            $table->bigInteger('storage_used')->default(0)->after('storage_quota');
            $table->boolean('is_active')->default(true)->after('storage_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'storage_quota', 'storage_used', 'is_active']);
        });
    }
};
