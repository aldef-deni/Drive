<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Migrasi perbaikan: pastikan skema yang dibutuhkan aplikasi mobile benar-benar ada.
 *
 * Latar belakang: server produksi pernah kehilangan file migrasi
 * `2024_01_01_000010_add_api_token_to_users_table.php` karena deploy dilakukan
 * dengan mengekstrak arsip berisi file yang berubah saja. Akibatnya
 * `php artisan migrate` menjawab "Nothing to migrate" — Laravel hanya melihat
 * file yang ada di disk — sementara kolom `api_token` tidak pernah terbentuk dan
 * setiap registrasi/login dari aplikasi gagal.
 *
 * Migrasi ini sengaja memeriksa keadaan nyata skema (hasColumn / hasTable),
 * bukan tabel `migrations`, sehingga aman dijalankan berapa kali pun dan pada
 * database yang sudah maupun belum lengkap.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'api_token')) {
            Schema::table('users', function (Blueprint $table) {
                $column = $table->string('api_token', 64)->nullable()->unique();

                // Kolom `avatar` sendiri berasal dari migrasi terpisah yang bisa
                // saja belum terpasang; posisikan setelahnya hanya bila ada.
                if (Schema::hasColumn('users', 'avatar')) {
                    $column->after('avatar');
                }
            });
        }

        // Isi token untuk akun yang belum punya, termasuk akun lama.
        DB::table('users')->whereNull('api_token')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'api_token' => Str::random(64),
            ]);
        });

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun: migrasi ini hanya menambal skema yang
        // hilang, dan pembuatnya yang asli tetap bertanggung jawab atas rollback.
    }
};
