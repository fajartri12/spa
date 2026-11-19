<?php

declare(strict_types=1);

use App\Enums\RoleName;

return [
    /*
    |--------------------------------------------------------------------------
    | Akun Demo
    |--------------------------------------------------------------------------
    |
    | Daftar kredensial yang ditampilkan pada panel "Akun Demo" di halaman
    | login. Akun ini hanya dibuat oleh UserSeeder pada lingkungan
    | local/testing, sehingga panel juga otomatis tersembunyi di produksi.
    |
    | Atribut "can" membatasi environment tempat akun tersebut benar-benar
    | dibuat, agar UI tidak pernah menampilkan akun yang tidak bisa dipakai.
    |
    */
    'accounts' => [
        [
            'role' => RoleName::SuperAdministrator,
            'email' => 'superadmin@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::Administrator,
            'email' => 'admin@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::Treasurer,
            'email' => 'bendahara@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::PaymentOfficer,
            'email' => 'petugas@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::Verifier,
            'email' => 'verifikator@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::Headmaster,
            'email' => 'kepsek@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
        [
            'role' => RoleName::AdmissionOperator,
            'email' => 'ppdb@email.com',
            'password' => 'password',
            'can' => ['local', 'testing'],
        ],
    ],
];