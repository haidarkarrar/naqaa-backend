<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyDataSeeder extends Seeder
{
    public function run(): void
    {
        $meditop = DB::connection('meditop');

        $meditop->table('TblSpecialities')->insertOrIgnore([
            ['Id' => 1, 'Name' => 'General Practice'],
            ['Id' => 2, 'Name' => 'Cardiology'],
            ['Id' => 3, 'Name' => 'Radiology'],
        ]);

        $meditop->table('TblDoctors')->insertOrIgnore([
            [
                'Id' => 1,
                'FirstName' => 'Alaa',
                'LastName' => 'Moussa',
                'FullName' => 'Dr. Alaa Moussa',
                'SpecialtyId' => 1,
                'Username' => 'alaa',
                'Password' => bcrypt('password123'),
                'Approved' => true,
                'Radiologist' => false,
            ],
            [
                'Id' => 2,
                'FirstName' => 'Layla',
                'LastName' => 'Haddad',
                'FullName' => 'Dr. Layla Haddad',
                'SpecialtyId' => 2,
                'Username' => 'layla',
                'Password' => bcrypt('password123'),
                'Approved' => true,
                'Radiologist' => false,
            ],
        ]);

        $patients = [
            [
                'Id' => 1,
                'First' => 'Sara',
                'Last' => 'Chahine',
                'GenderId' => 1,
                'DOB' => now()->subYears(34),
                'Phone' => '009611234567',
                'MainDoctorId' => 1,
            ],
            [
                'Id' => 2,
                'First' => 'Karim',
                'Last' => 'Bou Khalil',
                'GenderId' => 1,
                'DOB' => now()->subYears(41),
                'Phone' => '009611235000',
                'MainDoctorId' => 1,
            ],
            [
                'Id' => 3,
                'First' => 'Maya',
                'Last' => 'Jaber',
                'GenderId' => 2,
                'DOB' => now()->subYears(28),
                'Phone' => '009611239999',
                'MainDoctorId' => 1,
            ],
            [
                'Id' => 4,
                'First' => 'Hassan',
                'Last' => 'Nader',
                'GenderId' => 1,
                'DOB' => now()->subYears(50),
                'Phone' => '009611230123',
                'MainDoctorId' => 1,
            ],
        ];

        $meditop->table('TblPatients')->insertOrIgnore($patients);

        $meditop->table('TblGuarantors')->insertOrIgnore([
            ['Id' => 1, 'Name' => 'NMC Group', 'Active' => true, 'AccountId' => 1],
        ]);

        $meditop->table('TblAdmFiles')->insertOrIgnore([
            [
                'Id' => 100,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(2),
                'Posted' => false,
                'GrandTotal' => 2000,
                'PatientShare' => 500,
            ],
            [
                'Id' => 101,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(5),
                'Posted' => true,
                'GrandTotal' => 800,
                'PatientShare' => 200,
            ],
            [
                'Id' => 102,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(15),
                'Posted' => true,
                'GrandTotal' => 1500,
                'PatientShare' => 400,
            ],
            [
                'Id' => 103,
                'PatientId' => 1,
                'DoctorId' => 2,
                'AdmDate' => now()->subDays(20),
                'Posted' => true,
                'GrandTotal' => 1100,
                'PatientShare' => 300,
            ],
            [
                'Id' => 104,
                'PatientId' => 2,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(10),
                'Posted' => true,
                'GrandTotal' => 1500,
                'PatientShare' => 450,
            ],
            [
                'Id' => 105,
                'PatientId' => 3,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(30),
                'Posted' => true,
                'GrandTotal' => 500,
                'PatientShare' => 200,
            ],
            [
                'Id' => 106,
                'PatientId' => 4,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(60),
                'Posted' => true,
                'GrandTotal' => 1200,
                'PatientShare' => 600,
            ],
        ]);
    }
}
