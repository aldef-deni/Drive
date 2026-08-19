<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_folders', function (Blueprint $table) {
            $table->string('lock_password')->nullable()->after('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('file_folders', function (Blueprint $table) {
            $table->dropColumn('lock_password');
        });
    }
};
