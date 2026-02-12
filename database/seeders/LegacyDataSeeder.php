<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyDataSeeder extends Seeder
{
    /**
     * Insert records only if they don't exist (SQL Server compatible)
     * Handles IDENTITY_INSERT for SQL Server when inserting explicit IDs
     */
    private function insertIfNotExists($connection, $table, $data, $keyColumn = 'Id'): void
    {
        if (empty($data)) {
            return;
        }

        $driver = $connection->getDriverName();
        $needsIdentityInsert = $driver === 'sqlsrv' && isset($data[0][$keyColumn]);

        // Use transaction to ensure IDENTITY_INSERT is respected
        $connection->beginTransaction();
        
        try {
            // Enable IDENTITY_INSERT for SQL Server if needed
            if ($needsIdentityInsert) {
                // Use unprepared to ensure it executes immediately
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] ON");
            }

            foreach ($data as $record) {
                $keyValue = $record[$keyColumn] ?? null;
                if ($keyValue === null) {
                    continue;
                }

                $exists = $connection->table($table)
                    ->where($keyColumn, $keyValue)
                    ->exists();

                if (!$exists) {
                    if ($needsIdentityInsert) {
                        // Use raw SQL for inserts when IDENTITY_INSERT is enabled
                        // Laravel's insert() method doesn't respect IDENTITY_INSERT, so we use raw SQL
                        $columns = array_keys($record);
                        $values = array_values($record);
                        
                        $columnsStr = '[' . implode('], [', $columns) . ']';
                        $placeholders = array_fill(0, count($values), '?');
                        $placeholdersStr = implode(', ', $placeholders);
                        
                        $sql = "INSERT INTO [{$table}] ({$columnsStr}) VALUES ({$placeholdersStr})";
                        $connection->statement($sql, $values);
                    } else {
                        $connection->table($table)->insert($record);
                    }
                }
            }

            // Disable IDENTITY_INSERT before committing
            if ($needsIdentityInsert) {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }

            $connection->commit();
        } catch (\Exception $e) {
            // Disable IDENTITY_INSERT on error
            if ($needsIdentityInsert) {
                try {
                    $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
                } catch (\Exception $e2) {
                    // Ignore errors when disabling
                }
            }
            $connection->rollBack();
            throw $e;
        }
    }

    public function run(): void
    {
        $meditop = DB::connection('meditop');

        $this->insertIfNotExists($meditop, 'TblSpecialities', [
            ['Id' => 1, 'Name' => 'General Practice'],
            ['Id' => 2, 'Name' => 'Cardiology'],
            ['Id' => 3, 'Name' => 'Radiology'],
        ]);

        $this->insertIfNotExists($meditop, 'TblDoctors', [
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

        $this->insertIfNotExists($meditop, 'TblPatients', $patients);

        $this->insertIfNotExists($meditop, 'TblGuarantors', [
            ['Id' => 1, 'Name' => 'NMC Group', 'Active' => true, 'AccountId' => 1],
        ]);

        $this->insertIfNotExists($meditop, 'TblAdmFiles', [
            [
                'Id' => 100,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(2),
                'Closed' => false,
                'GrandTotal' => 2000,
                'PatientShare' => 500,
            ],
            [
                'Id' => 101,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(5),
                'Closed' => true,
                'GrandTotal' => 800,
                'PatientShare' => 200,
            ],
            [
                'Id' => 102,
                'PatientId' => 1,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(15),
                'Closed' => true,
                'GrandTotal' => 1500,
                'PatientShare' => 400,
            ],
            [
                'Id' => 103,
                'PatientId' => 1,
                'DoctorId' => 2,
                'AdmDate' => now()->subDays(20),
                'Closed' => true,
                'GrandTotal' => 1100,
                'PatientShare' => 300,
            ],
            [
                'Id' => 104,
                'PatientId' => 2,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(10),
                'Closed' => true,
                'GrandTotal' => 1500,
                'PatientShare' => 450,
            ],
            [
                'Id' => 105,
                'PatientId' => 3,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(30),
                'Closed' => true,
                'GrandTotal' => 500,
                'PatientShare' => 200,
            ],
            [
                'Id' => 106,
                'PatientId' => 4,
                'DoctorId' => 1,
                'AdmDate' => now()->subDays(60),
                'Closed' => true,
                'GrandTotal' => 1200,
                'PatientShare' => 600,
            ],
        ]);
    }
}
