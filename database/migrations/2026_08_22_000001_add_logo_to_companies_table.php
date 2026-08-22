<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies') || Schema::hasColumn('companies', 'logo')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            // Hanya nama berkasnya; direktorinya ditentukan aplikasi supaya
            // pemindahan lokasi penyimpanan tidak perlu menyentuh database.
            $table->string('logo')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'logo')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('logo');
            });
        }
    }
};
