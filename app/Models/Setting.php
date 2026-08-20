<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Penyimpanan pengaturan aplikasi sederhana (key-value).
 */
class Setting extends Model
{
    /** Kata kunci rahasia untuk memunculkan file/folder tersembunyi. */
    public const HIDDEN_KEYWORD = 'hidden_keyword';

    /** Kata kunci bawaan bila admin belum pernah menggantinya. */
    public const DEFAULT_HIDDEN_KEYWORD = 'deniafrizal';

    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'setting:';

    /**
     * Ambil sebuah pengaturan.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = Cache::rememberForever(self::CACHE_PREFIX . $key, function () use ($key) {
                // null tidak bisa dibedakan dari "tidak ada" di cache, jadi disimpan
                // sebagai array pembungkus.
                return ['value' => static::query()->where('key', $key)->value('value')];
            });
        } catch (\Throwable $e) {
            // Tabel settings belum dimigrasi (mis. baru unggah file ke server).
            // Jangan sampai seluruh halaman ikut error karenanya.
            return $default;
        }

        return $value['value'] ?? $default;
    }

    /**
     * Simpan sebuah pengaturan.
     */
    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Simpan kata kunci rahasia dalam bentuk hash.
     */
    public static function setHiddenKeyword(string $keyword): void
    {
        static::put(self::HIDDEN_KEYWORD, Hash::make($keyword));
    }

    /**
     * Apakah teks yang diketik pengguna cocok dengan kata kunci rahasia?
     *
     * Selama admin belum pernah menggantinya, kata kunci bawaan tetap berlaku
     * agar drive yang sudah berjalan tidak kehilangan akses ke file tersembunyi.
     */
    public static function matchesHiddenKeyword(string $input): bool
    {
        $input = trim($input);

        // Saringan murah lebih dulu: kata kunci tidak pernah memuat spasi dan
        // panjangnya dibatasi, jadi mayoritas pencarian biasa tidak perlu
        // sampai memanggil bcrypt yang mahal.
        if ($input === ''
            || mb_strlen($input) < 4
            || mb_strlen($input) > 64
            || preg_match('/\s/u', $input)) {
            return false;
        }

        $hash = static::get(self::HIDDEN_KEYWORD);

        if (!$hash) {
            return hash_equals(self::DEFAULT_HIDDEN_KEYWORD, $input);
        }

        return Hash::check($input, $hash);
    }

    /**
     * Kapan kata kunci terakhir diubah (null bila masih bawaan).
     */
    public static function hiddenKeywordUpdatedAt(): ?\Illuminate\Support\Carbon
    {
        return static::query()->where('key', self::HIDDEN_KEYWORD)->value('updated_at');
    }
}
