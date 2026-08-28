<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Demo
    |--------------------------------------------------------------------------
    |
    | Satu akun admin yang boleh dicoba siapa saja. Datanya dikembalikan ke
    | keadaan semula setiap kali ada yang masuk dan jarak dari pemulihan
    | terakhir sudah melewati `reset_after_hours`.
    |
    | Kosongkan `email` untuk mematikan seluruh fitur demo.
    |
    */

    'email' => env('DEMO_EMAIL', 'demo@aldeftech.com'),

    'password' => env('DEMO_PASSWORD', 'demo12345'),

    'name' => 'Akun Demo',

    /*
    | Perusahaan tersendiri, bukan perusahaan yang dipakai pelanggan.
    | Akun demo berperan admin, dan admin melihat seluruh pengguna di
    | perusahaannya - menempatkannya di perusahaan asli berarti membuka data
    | pelanggan kepada siapa pun yang mencoba demo.
    */
    'company' => 'Aldef Tech Demo',

    'company_quota_gb' => 1,

    'reset_after_hours' => 24,

];
