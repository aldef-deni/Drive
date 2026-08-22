<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('files') && !Schema::hasColumn('files', 'is_starred')) {
            Schema::table('files', function (Blueprint $table) {
                $table->boolean('is_starred')->default(false)->after('is_hidden');
                // Halaman Berbintang menyaring dua kolom ini bersamaan.
                $table->index(['user_id', 'is_starred']);
            });
        }

        if (Schema::hasTable('file_folders') && !Schema::hasColumn('file_folders', 'is_starred')) {
            Schema::table('file_folders', function (Blueprint $table) {
                $table->boolean('is_starred')->default(false)->after('is_hidden');
                $table->index(['user_id', 'is_starred']);
            });
        }
    }

    public function down(): void
    {
        foreach (['files', 'file_folders'] as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'is_starred')) {
                Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                    $table->dropIndex($tabel . '_user_id_is_starred_index');
                    $table->dropColumn('is_starred');
                });
            }
        }
    }
};
