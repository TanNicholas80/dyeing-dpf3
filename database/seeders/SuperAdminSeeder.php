<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    /**
     * Jalankan seeder untuk membuat akun super admin.
     */
    public function run(): void
    {
        // Hapus user lama dengan username ini jika sudah ada (opsional)
        User::where('username', 'superadmin')->delete();

        // Buat user super admin
        User::create([
            'nama' => 'Super Admin',
            'username' => 'DT_VIN',
            'password' => '123456', 
            'role' => 'super_admin',
            'mesin' => null,
        ]);

    }
}
