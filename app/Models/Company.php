<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Perusahaan penyewa (tenant) Dekorasi Drive.
 *
 * Pemisahan data mengalir lewat pengguna: setiap file, folder, dan notifikasi
 * dimiliki seorang pengguna, dan setiap pengguna terikat pada satu perusahaan.
 * Admin hanya melihat pengguna di perusahaannya sendiri.
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'address',
        'default_quota',
        'max_users',
        'is_active',
    ];

    protected $casts = [
        'default_quota' => 'integer',
        'max_users' => 'integer',
        'is_active' => 'boolean',
    ];

    /** Direktori penyimpanan logo, di luar public/ agar tidak butuh symlink. */
    public const LOGO_DIR = 'app/public/company-logos';

    /**
     * Lokasi berkas logo di disk, atau null bila tidak ada/hilang.
     */
    public function logoPath(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        // Nama berkas dinormalkan: data lama sempat menyimpan jalur lengkap.
        $nama = basename(str_replace(chr(92), '/', $this->logo));
        $path = storage_path(self::LOGO_DIR . '/' . $nama);

        return is_file($path) ? $path : null;
    }

    /**
     * Alamat untuk menampilkan logo.
     *
     * Lewat route, bukan asset('storage/...'), karena symlink tidak bisa
     * diandalkan di cPanel - sama seperti penyajian avatar.
     */
    public function logoUrl(): ?string
    {
        if (!$this->logoPath()) {
            return null;
        }

        return route('company.logo', [
            'company' => $this->id,
            'v' => $this->updated_at?->timestamp ?? 0,
        ]);
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company) {
            // Slug kosong selalu dibuatkan dari nama.
            if (empty($company->slug)) {
                $company->slug = static::uniqueSlug($company->name, $company->id);

                return;
            }

            // Nama berubah tanpa slug diisi manual -> slug ikut menyesuaikan.
            // Slug yang ditentukan eksplisit tidak boleh ditimpa; itu membuat
            // pemanggil seperti firstOrCreate(['slug' => ...]) tidak pernah
            // menemukan barisnya lagi.
            if ($company->isDirty('name') && !$company->isDirty('slug')) {
                $company->slug = static::uniqueSlug($company->name, $company->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $abaikanId = null): string
    {
        $dasar = Str::slug($name) ?: 'perusahaan';
        $slug = $dasar;
        $n = 2;

        while (static::where('slug', $slug)
            ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
            ->exists()
        ) {
            $slug = $dasar . '-' . $n++;
        }

        return $slug;
    }

    /**
     * Kuota bawaan dalam GB, tanpa nol berekor.
     * number_format memaksa "100.0"; angka bulat sebaiknya tampil "100".
     */
    public function defaultQuotaGb(): string
    {
        return rtrim(rtrim(number_format($this->default_quota / 1073741824, 2, '.', ''), '0'), '.');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** File milik seluruh pengguna perusahaan ini. */
    public function files()
    {
        return File::whereIn('user_id', $this->users()->select('id'));
    }

    /** Total penyimpanan terpakai seluruh pengguna. */
    public function storageUsed(): int
    {
        return (int) $this->users()->sum('storage_used');
    }

    public function activeUsersCount(): int
    {
        return $this->users()->where('is_active', true)->count();
    }

    public function pendingUsersCount(): int
    {
        return $this->users()->where('is_active', false)->count();
    }

    /** Apakah kuota jumlah akun sudah penuh? */
    public function isFull(): bool
    {
        return $this->max_users !== null && $this->users()->count() >= $this->max_users;
    }
}
