<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

    /** Penanda nilai terenkripsi, membedakannya dari hash bcrypt lama. */
    private const ENC_PREFIX = 'enc:';

    /**
     * Simpan kata kunci rahasia dalam bentuk terenkripsi.
     *
     * Sengaja dienkripsi, bukan di-hash, supaya superadministrator bisa melihat
     * kata kunci yang sedang berlaku — tanpa itu, satu-satunya jalan keluar saat
     * lupa adalah menggantinya dan memberi tahu ulang seluruh pengguna.
     *
     * Konsekuensinya nilai ini bisa dibuka siapa pun yang memegang database
     * DAN APP_KEY sekaligus. Kata kunci ini memang kode akses bersama, bukan
     * kredensial pribadi, jadi pertukaran itu dapat diterima.
     */
    public static function setHiddenKeyword(string $keyword): void
    {
        static::put(self::HIDDEN_KEYWORD, self::ENC_PREFIX . Crypt::encryptString($keyword));
    }

    /** Belum pernah diganti, kata kunci bawaan yang berlaku. */
    public const STATE_DEFAULT = 'default';

    /** Tersimpan terenkripsi dan bisa ditampilkan. */
    public const STATE_READABLE = 'readable';

    /** Hash versi lama: masih berlaku, tetapi tidak bisa dibaca balik. */
    public const STATE_LEGACY = 'legacy';

    /** Terenkripsi tetapi gagal dibuka — APP_KEY berubah setelah disimpan. */
    public const STATE_UNREADABLE = 'unreadable';

    /**
     * Bentuk penyimpanan kata kunci saat ini.
     *
     * Dibedakan karena pesannya ke pengguna berbeda: hash versi lama masih
     * berlaku dan hanya perlu diganti sekali, sedangkan nilai yang gagal
     * dibuka berarti kata kuncinya benar-benar sudah tidak berfungsi.
     */
    public static function hiddenKeywordState(): string
    {
        $nilai = static::get(self::HIDDEN_KEYWORD);

        if (!$nilai) {
            return self::STATE_DEFAULT;
        }

        if (!str_starts_with($nilai, self::ENC_PREFIX)) {
            return self::STATE_LEGACY;
        }

        return static::bukaTerenkripsi($nilai) === null
            ? self::STATE_UNREADABLE
            : self::STATE_READABLE;
    }

    /**
     * Kata kunci yang sedang berlaku, apa adanya.
     *
     * Mengembalikan null bila nilainya belum bisa ditampilkan — lihat
     * hiddenKeywordState() untuk alasannya.
     */
    public static function hiddenKeywordPlain(): ?string
    {
        $nilai = static::get(self::HIDDEN_KEYWORD);

        if (!$nilai) {
            return self::DEFAULT_HIDDEN_KEYWORD;
        }

        if (!str_starts_with($nilai, self::ENC_PREFIX)) {
            return null; // hash lama, tidak bisa dipulihkan
        }

        return static::bukaTerenkripsi($nilai);
    }

    private static function bukaTerenkripsi(string $nilai): ?string
    {
        try {
            return Crypt::decryptString(substr($nilai, strlen(self::ENC_PREFIX)));
        } catch (\Throwable $e) {
            // APP_KEY berubah setelah nilai ini disimpan.
            return null;
        }
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

        $tersimpan = static::get(self::HIDDEN_KEYWORD);

        if (!$tersimpan) {
            return hash_equals(self::DEFAULT_HIDDEN_KEYWORD, $input);
        }

        // Nilai terenkripsi dibandingkan apa adanya.
        if (str_starts_with($tersimpan, self::ENC_PREFIX)) {
            $asli = self::hiddenKeywordPlain();

            return $asli !== null && hash_equals($asli, $input);
        }

        // Kata kunci yang disimpan versi lama tetap berlaku sampai diganti.
        return Hash::check($input, $tersimpan);
    }

    /**
     * Kapan kata kunci terakhir diubah (null bila masih bawaan).
     */
    public static function hiddenKeywordUpdatedAt(): ?\Illuminate\Support\Carbon
    {
        return static::query()->where('key', self::HIDDEN_KEYWORD)->value('updated_at');
    }
}
