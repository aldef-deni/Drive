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

    protected static function booted(): void
    {
        // Slug dibuat otomatis dan dijaga unik, termasuk saat nama diubah.
        static::saving(function (Company $company) {
            if (empty($company->slug) || $company->isDirty('name')) {
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
