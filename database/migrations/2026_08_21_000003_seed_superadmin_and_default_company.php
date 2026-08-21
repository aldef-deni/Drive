<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Siapkan superadministrator dan pindahkan akun lama ke satu perusahaan.
 *
 * Akun lama tidak punya `company_id`. Tanpa langkah ini mereka akan menggantung
 * tanpa perusahaan dan tidak terlihat oleh admin mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perusahaan = Company::firstOrCreate(
            ['slug' => 'dekorasi-me'],
            [
                'name' => 'Dekorasi.me',
                'email' => 'admin@dekorasi.me',
                'default_quota' => User::DEFAULT_STORAGE_QUOTA,
                'is_active' => true,
            ]
        );

        // Semua akun yang belum punya perusahaan masuk ke perusahaan bawaan.
        DB::table('users')->whereNull('company_id')->update(['company_id' => $perusahaan->id]);

        // Superadministrator: satu-satunya peran di atas admin perusahaan.
        // Password hanya diisi saat akun dibuat, sehingga penggantian password
        // di kemudian hari tidak tertimpa saat migrasi dijalankan ulang.
        $superadmin = User::where('username', 'deniafrizal')->first();

        if (!$superadmin) {
            User::create([
                'company_id' => null,
                'name' => 'Deni Afrizal',
                'username' => 'deniafrizal',
                'email' => 'deniafrizal@dekorasi.me',
                'password' => Hash::make('p0o9i8u7'),
                'role' => User::ROLE_SUPERADMIN,
                'storage_quota' => 10737418240,
                'storage_used' => 0,
                'is_active' => true,
            ]);
        } else {
            $superadmin->forceFill([
                'role' => User::ROLE_SUPERADMIN,
                'company_id' => null,
                'is_active' => true,
            ])->save();
        }
    }

    public function down(): void
    {
        User::where('username', 'deniafrizal')->delete();
        Company::where('slug', 'dekorasi-me')->delete();
    }
};
