<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lihat atau tetapkan kata kunci rahasia langsung dari server.
 *
 * Dibuat karena jalur web punya banyak lapisan yang bisa diam-diam menggagalkan
 * penyimpanan (cache, opcache, sesi, kode lama yang belum terganti). Perintah
 * ini memotong semuanya dan sekaligus melaporkan apa yang benar-benar ada di
 * dalam database.
 */
class HiddenKeyword extends Command
{
    protected $signature = 'drive:hidden-keyword
                            {keyword? : Kata kunci baru. Kosongkan untuk sekadar melihat.}';

    protected $description = 'Lihat atau tetapkan kata kunci rahasia Hidden System';

    public function handle(): int
    {
        $baru = $this->argument('keyword');

        if ($baru !== null) {
            $baru = trim($baru);

            if (mb_strlen($baru) < 4 || mb_strlen($baru) > 64 || preg_match('/\s/u', $baru)) {
                $this->error('Kata kunci harus 4-64 karakter dan tanpa spasi.');

                return self::FAILURE;
            }

            Setting::setHiddenKeyword($baru);
            $this->newLine();
            $this->info('Kata kunci disimpan.');
        }

        $this->laporkan();

        return self::SUCCESS;
    }

    private function laporkan(): void
    {
        // Sengaja lewat query mentah, bukan Setting::get(), supaya yang
        // dilaporkan adalah isi database - bukan salinan di cache.
        $baris = DB::table('settings')->where('key', Setting::HIDDEN_KEYWORD)->first();

        $this->newLine();
        $this->line('<comment>Kata Kunci Hidden System</comment>');
        $this->line(str_repeat('-', 46));

        if (!$baris) {
            $this->line('Status        : belum pernah diganti');
            $this->line('Kata kunci    : <info>' . Setting::DEFAULT_HIDDEN_KEYWORD . '</info> (bawaan)');
            $this->newLine();
            $this->warn('Kata kunci bawaan diketahui banyak orang. Sebaiknya diganti:');
            $this->line('  php artisan drive:hidden-keyword "kunciPilihanAnda"');
            $this->newLine();

            return;
        }

        $panjang = strlen((string) $baris->value);
        $bentuk = str_starts_with((string) $baris->value, 'enc:') ? 'terenkripsi' : 'hash versi lama';

        $this->line('Terakhir diubah: ' . $baris->updated_at);
        $this->line('Bentuk simpan  : ' . $bentuk . ' (' . $panjang . ' karakter)');

        $nilai = Setting::hiddenKeywordPlain();

        if ($nilai !== null) {
            $this->line('Kata kunci     : <info>' . $nilai . '</info>');
            $this->newLine();

            return;
        }

        $this->newLine();

        if ($bentuk === 'hash versi lama') {
            $this->warn('Kata kunci ini belum bisa ditampilkan.');
            $this->line('Disimpan satu arah oleh versi lama, jadi tidak bisa dibaca balik.');
        } else {
            $this->error('Kata kunci gagal dibuka - APP_KEY berubah setelah disimpan.');
            $this->line('Kata kunci lama juga sudah tidak berfungsi lagi.');
        }

        $this->line('Tetapkan yang baru:');
        $this->line('  php artisan drive:hidden-keyword "kunciPilihanAnda"');
        $this->newLine();
    }
}
