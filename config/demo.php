<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Demo
    |--------------------------------------------------------------------------
    |
    | Satu tenant tersendiri yang boleh dicoba siapa saja. Seluruh isinya
    | dibangun ulang setiap kali ada yang masuk dan jarak dari pemulihan
    | terakhir sudah melewati `reset_after_hours`.
    |
    | Kosongkan `username` untuk mematikan seluruh fitur demo.
    |
    */

    'username' => env('DEMO_USERNAME', 'demo'),

    'password' => env('DEMO_PASSWORD', 'demo12345'),

    'email' => env('DEMO_EMAIL', 'demo@aldeftech.com'),

    'name' => 'Owner Demo',

    /*
    | Tenant tersendiri, bukan milik pelanggan. Akun demo berperan Owner, dan
    | Owner melihat seluruh outlet beserta laporan labanya - menaruhnya di
    | tenant asli berarti membuka data penjualan pelanggan kepada siapa pun
    | yang mencoba demo.
    */
    'tenant_slug' => 'demo-aldeftech',
    'tenant_name' => 'Toko Demo Aldef Tech',

    'reset_after_hours' => 24,

    /*
    | Banyaknya isi contoh. Sengaja jauh lebih ringan daripada DatabaseSeeder:
    | pemulihan ini berjalan di tengah permintaan masuk, jadi tiga minggu
    | transaksi di tiga outlet akan membuat pengunjung menunggu terlalu lama
    | sebelum halaman pertamanya terbuka.
    */
    'outlets' => 2,
    'sales_days' => 7,
    'max_sales_per_day' => 6,

];
