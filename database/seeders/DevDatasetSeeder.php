<?php

namespace Database\Seeders;

use App\Models\AdmissionAttachment;
use App\Models\DigitalAdmissionForm;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DevDatasetSeeder extends Seeder
{
    private const PASSWORD_HASH = '$2y$12$ka1tHidRyes3QMHcsGJi6OyW2oFXLteEGlwMDjls/6MhSzzME4K5y';
    private const FORM_VERSION = 'dev-seed-v1';
    private const TINY_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEBAPEA8PDw8PDw8PDw8PDw8QFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGi0fHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAgMBEQACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEh/9oADAMBAAIQAxAAAAGdAP/EABkQAQADAQEAAAAAAAAAAAAAAAERAhIhMf/aAAgBAQABBQJr4S6n/8QAFhEBAQEAAAAAAAAAAAAAAAAAARAR/9oACAEDAQE/Aaf/xAAVEQEBAAAAAAAAAAAAAAAAAAABEP/aAAgBAgEBPwGn/8QAGRAAAgMBAAAAAAAAAAAAAAAAAQIAAxEh/9oACAEBAAY/AqN7F//EABkQAQADAQEAAAAAAAAAAAAAAAEAESExQf/aAAgBAQABPyGGuM3M5Gm8o//aAAwDAQACAAMAAAAQ8//EABgRAQEBAQEAAAAAAAAAAAAAAAERABAh/9oACAEDAQE/EKk6b//EABYRAQEBAAAAAAAAAAAAAAAAAAABEf/aAAgBAgEBPxBqP//EABsQAQACAgMAAAAAAAAAAAAAAAEAESExQVFh/9oACAEBAAE/EFhqt0Uoy4oecm//2Q==';

    /**
     * Insert records only if they don't exist (SQL Server compatible).
     */
    private function insertIfNotExists($connection, string $table, array $data, string $keyColumn = 'Id'): void
    {
        if (empty($data)) {
            return;
        }

        $driver = $connection->getDriverName();
        $hasExplicitId = isset($data[0][$keyColumn]);
        $needsIdentityInsert = false;

        if ($driver === 'sqlsrv' && $hasExplicitId) {
            try {
                $tableVariations = [
                    "dbo.[{$table}]",
                    "[dbo].[{$table}]",
                    $table,
                    "[{$table}]",
                ];

                $result = null;
                foreach ($tableVariations as $tableName) {
                    $result = $connection->selectOne(
                        'SELECT is_identity FROM sys.columns WHERE object_id = OBJECT_ID(?) AND name = ?',
                        [$tableName, $keyColumn]
                    );

                    if ($result !== null) {
                        break;
                    }
                }

                $needsIdentityInsert = $result && isset($result->is_identity) && $result->is_identity == 1;
            } catch (\Throwable) {
                $needsIdentityInsert = false;
            }
        }

        $connection->beginTransaction();

        try {
            if ($needsIdentityInsert) {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] ON");
            }

            foreach ($data as $record) {
                $keyValue = $record[$keyColumn] ?? null;
                if ($keyValue === null) {
                    continue;
                }

                $exists = $connection->table($table)->where($keyColumn, $keyValue)->exists();
                if ($exists) {
                    continue;
                }

                if ($needsIdentityInsert) {
                    $columns = array_keys($record);
                    $values = array_values($record);
                    $columnsSql = '[' . implode('], [', $columns) . ']';
                    $placeholdersSql = implode(', ', array_fill(0, count($values), '?'));
                    $connection->statement(
                        "INSERT INTO [{$table}] ({$columnsSql}) VALUES ({$placeholdersSql})",
                        $values
                    );
                } else {
                    $connection->table($table)->insert($record);
                }
            }

            if ($needsIdentityInsert) {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($needsIdentityInsert) {
                try {
                    $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
                } catch (\Throwable) {
                }
            }

            $connection->rollBack();
            throw $exception;
        }
    }

    public function run(): void
    {
        $this->call([AccessControlSeeder::class]);

        $meditop = DB::connection('meditop');
        $archive = DB::connection('archive');

        $beirutToday = CarbonImmutable::now('Asia/Beirut')->startOfDay();
        $doctorAssignments = $this->buildDoctorAssignments();

        $this->insertIfNotExists($meditop, 'TblDoctors', $this->buildDoctors());
        $this->insertIfNotExists($meditop, 'TblPatients', $this->buildPatients($beirutToday, $doctorAssignments));
        $this->insertIfNotExists($meditop, 'TblCheckLists', $this->buildChecklists());
        $this->insertIfNotExists($meditop, 'TblCheckListItems', $this->buildChecklistItems());
        $this->insertIfNotExists($meditop, 'TblPatientCheckedItems', $this->buildPatientCheckedItems($beirutToday));
        $this->insertIfNotExists($meditop, 'TblAdmFiles', $this->buildAdmissions($beirutToday, $doctorAssignments));
        $this->insertIfNotExists($meditop, 'tblWorks', $this->buildWorks($doctorAssignments));
        $this->seedLegacyDocuments($archive, $this->buildLegacyDocuments($beirutToday));

        $this->seedUsers();
        $this->seedDigitalForms($beirutToday, $doctorAssignments);
        $this->seedAttachments($beirutToday, $doctorAssignments);

        if ($this->command) {
            $this->command->info('Dev dataset seeded.');
        }
    }

    private function buildDoctorAssignments(): array
    {
        $newDoctorIds = range(1001, 1024);
        $assignments = [];

        foreach (range(1, 49) as $index) {
            $assignments[$index] = $index <= 18
                ? 1
                : $newDoctorIds[($index - 19) % count($newDoctorIds)];
        }

        return $assignments;
    }

    private function buildDoctors(): array
    {
        $firstNames = [
            'Rami', 'Nadine', 'Karim', 'Lina', 'Hadi', 'Maya',
            'Tarek', 'Samar', 'Ziad', 'Rana', 'Walid', 'Mira',
        ];
        $lastNames = [
            'Haddad', 'Mansour', 'Khoury', 'Saade', 'Nassar', 'Helou',
            'Farah', 'Karam', 'Yared', 'Azar', 'Maalouf', 'Saliba',
        ];

        $records = [];
        foreach (range(1001, 1024) as $offset => $doctorId) {
            $firstName = $firstNames[$offset % count($firstNames)];
            $lastName = $lastNames[$offset % count($lastNames)];
            $username = sprintf('seed_doctor_%d', $doctorId);

            $records[] = [
                'Id' => $doctorId,
                'FirstName' => $firstName,
                'MiddleName' => null,
                'LastName' => $lastName,
                'FullName' => sprintf('Dr. %s %s', $firstName, $lastName),
                'Username' => $username,
                'Email' => $username . '@example.com',
                'SpecialtyId' => ($offset % 6) + 1,
                'Radiologist' => 0,
                'Approved' => 1,
                'Password' => self::PASSWORD_HASH,
            ];
        }

        return $records;
    }

    private function buildChecklists(): array
    {
        return [
            [
                'Id' => 6001,
                'Name' => 'Allergies',
                'Description' => 'Common allergy history used in the dev dataset.',
            ],
            [
                'Id' => 6002,
                'Name' => 'Habits',
                'Description' => 'Lifestyle and exposure items used in the dev dataset.',
            ],
            [
                'Id' => 6003,
                'Name' => 'Past Medical History',
                'Description' => 'Representative chronic and prior medical conditions.',
            ],
            [
                'Id' => 6004,
                'Name' => 'Surgical History',
                'Description' => 'Representative surgical history items for local testing.',
            ],
        ];
    }

    private function buildChecklistItems(): array
    {
        return [
            ['Id' => 6101, 'CheckListId' => 6001, 'Name' => 'Penicillin', 'Description' => 'Penicillin allergy'],
            ['Id' => 6102, 'CheckListId' => 6001, 'Name' => 'NSAIDs', 'Description' => 'NSAID allergy'],
            ['Id' => 6103, 'CheckListId' => 6001, 'Name' => 'Seafood', 'Description' => 'Seafood allergy'],
            ['Id' => 6104, 'CheckListId' => 6001, 'Name' => 'Latex', 'Description' => 'Latex allergy'],
            ['Id' => 6201, 'CheckListId' => 6002, 'Name' => 'Smoker', 'Description' => 'Current smoker'],
            ['Id' => 6202, 'CheckListId' => 6002, 'Name' => 'Alcohol use', 'Description' => 'Alcohol use history'],
            ['Id' => 6203, 'CheckListId' => 6002, 'Name' => 'Shisha', 'Description' => 'Shisha use'],
            ['Id' => 6204, 'CheckListId' => 6002, 'Name' => 'Occupational exposure', 'Description' => 'Exposure at work'],
            ['Id' => 6301, 'CheckListId' => 6003, 'Name' => 'Asthma', 'Description' => 'History of asthma'],
            ['Id' => 6302, 'CheckListId' => 6003, 'Name' => 'Hypertension', 'Description' => 'History of hypertension'],
            ['Id' => 6303, 'CheckListId' => 6003, 'Name' => 'Ischemic heart disease', 'Description' => 'IHD history'],
            ['Id' => 6304, 'CheckListId' => 6003, 'Name' => 'Epilepsy', 'Description' => 'History of epilepsy'],
            ['Id' => 6305, 'CheckListId' => 6003, 'Name' => 'Thyroid disease', 'Description' => 'History of thyroid disease'],
            ['Id' => 6401, 'CheckListId' => 6004, 'Name' => 'Appendectomy', 'Description' => 'Prior appendectomy'],
            ['Id' => 6402, 'CheckListId' => 6004, 'Name' => 'C-section', 'Description' => 'Prior caesarean section'],
            ['Id' => 6403, 'CheckListId' => 6004, 'Name' => 'Cholecystectomy', 'Description' => 'Prior cholecystectomy'],
            ['Id' => 6404, 'CheckListId' => 6004, 'Name' => 'Orthopedic surgery', 'Description' => 'Prior orthopedic surgery'],
        ];
    }

    private function buildPatientCheckedItems(CarbonImmutable $beirutToday): array
    {
        $selectionMap = [
            2001 => [6101, 6301],
            2002 => [6201, 6203],
            2003 => [6302, 6401],
            2004 => [6102, 6303, 6403],
            2005 => [6202],
            2006 => [6103, 6305],
            2008 => [6204, 6404],
            2010 => [6104, 6201, 6302],
            2013 => [6301, 6304],
            2015 => [6402],
            2021 => [6101],
            2022 => [6103, 6202],
            2027 => [6201, 6302, 6401],
            2033 => [6304],
            2038 => [6102, 6404],
            2041 => [6203, 6305],
            2046 => [6104, 6204],
        ];

        $records = [];
        $id = 6501;

        foreach ($selectionMap as $patientId => $itemIds) {
            foreach ($itemIds as $position => $itemId) {
                $records[] = [
                    'Id' => $id++,
                    'PatientId' => $patientId,
                    'ItemId' => $itemId,
                    'Date' => $beirutToday
                        ->subDays(($patientId + $position) % 14)
                        ->setTime(9 + ($position % 6), 10 + (($patientId + $position) % 40))
                        ->utc()
                        ->format('Y-m-d H:i:s'),
                    'Note' => null,
                ];
            }
        }

        return $records;
    }

    private function buildPatients(CarbonImmutable $beirutToday, array $doctorAssignments): array
    {
        $childOrdinals = [1, 2, 21, 22, 33, 34, 45, 46];
        $firstNames = [
            'Adam', 'Mia', 'Omar', 'Lea', 'Noah', 'Sara', 'Jude', 'Rita', 'Eli', 'Nour',
        ];
        $lastNames = [
            'Amin', 'Barakat', 'Daher', 'Fares', 'Geagea', 'Issa', 'Khalil', 'Nasr', 'Sayegh', 'Younes',
        ];

        $records = [];
        foreach (range(1, 49) as $index) {
            $patientId = 2000 + $index;
            $isChild = in_array($index, $childOrdinals, true);
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNames[($index - 1) % count($lastNames)];
            $dob = $isChild
                ? $beirutToday->subMonths(6 + (($index - 1) % 16))->toDateString()
                : $beirutToday->subYears(20 + (($index - 1) % 42))->subDays($index)->toDateString();

            $records[] = [
                'Id' => $patientId,
                'First' => $firstName,
                'Middle' => null,
                'Last' => $lastName,
                'Mother' => sprintf('Mother %02d', $index),
                'GenderId' => ($index % 2) + 1,
                'Weight' => $isChild ? 8 + ($index % 5) : 55 + (($index * 3) % 40),
                'DOB' => $dob,
                'POB' => 'Beirut',
                'IDNum' => sprintf('PID%05d', $patientId),
                'NationalityId' => 1,
                'BloodGroupId' => (($index - 1) % 8) + 1,
                'ArabicName' => sprintf('%s %s', $firstName, $lastName),
                'Phone' => sprintf('70%06d', $index),
                'Email' => sprintf('patient_%02d@example.com', $index),
                'City' => 'Beirut',
                'Street' => sprintf('Street %02d', $index),
                'HomeTel' => null,
                'Address' => sprintf('Building %02d, Beirut', $index),
                'JobTel' => null,
                'GuarantorId' => null,
                'MaritalStatusId' => $isChild ? null : (($index - 1) % 4) + 1,
                'OFD' => null,
                'MainDoctorId' => $doctorAssignments[$index],
                'Diabetic' => $isChild ? 0 : ($index % 8 === 0 ? 1 : 0),
                'Pregnancy' => !$isChild && $index % 9 === 0 ? 1 : 0,
                'CardiacFailure' => $isChild ? 0 : ($index % 11 === 0 ? 1 : 0),
                'RenalFailure' => $isChild ? 0 : ($index % 13 === 0 ? 1 : 0),
                'OtherDisease' => $index % 10 === 0 ? 1 : 0,
                'AttachmentId' => null,
            ];
        }

        return $records;
    }

    private function buildAdmissions(CarbonImmutable $beirutToday, array $doctorAssignments): array
    {
        $times = [
            [0, 12],
            [3, 6],
            [8, 40],
            [14, 51],
            [20, 59],
            [23, 59],
        ];

        $records = [];
        foreach (range(1, 49) as $index) {
            $patientId = 2000 + $index;
            $admissionId = 3000 + $index;
            [$hour, $minute] = $times[($index - 1) % count($times)];
            $admDateUtc = $beirutToday
                ->subDays(($index - 1) % 28)
                ->setTime($hour, $minute)
                ->utc()
                ->format('Y-m-d H:i:s');

            $records[] = [
                'Id' => $admissionId,
                'PatientId' => $patientId,
                'DoctorId' => $doctorAssignments[$index],
                'GuarantorId' => null,
                'AdmDate' => $admDateUtc,
                'Closed' => $index % 4 === 0 ? 1 : 0,
                'Posted' => 0,
                'PaymentClosed' => 0,
                'PaymentClosed1' => 0,
                'ForDoctor' => 0,
                'ForPatient' => 0,
                'Checked' => $index % 6 === 0 ? 1 : 0,
                'Approved' => 1,
                'LastPostState' => 0,
            ];
        }

        return $records;
    }

    private function buildWorks(array $doctorAssignments): array
    {
        $records = [];

        foreach (range(1, 49) as $index) {
            $records[] = [
                'Id' => 5000 + $index,
                'AdmId' => 3000 + $index,
                'DoctorId' => $doctorAssignments[$index],
            ];
        }

        return $records;
    }

    private function buildLegacyDocuments(CarbonImmutable $beirutToday): array
    {
        $jpegBytes = base64_decode(self::TINY_JPEG_BASE64);
        $directOrdinals = [1, 2, 3, 4, 13, 14, 15, 16, 25, 26, 27, 28];
        $mrnOnlyOrdinals = [21, 22, 33, 34, 45, 46];
        $records = [];

        foreach ($directOrdinals as $ordinal) {
            $patientId = 2000 + $ordinal;
            $admissionId = 3000 + $ordinal;
            $dateUtc = $beirutToday->subDays(($ordinal - 1) % 28)->setTime(11, 15)->utc()->format('Y-m-d H:i:s');

            $records[] = [
                'AdmNb' => $admissionId,
                'MRN' => $patientId,
                'Date' => $dateUtc,
                'Document' => $jpegBytes,
                'Tump' => $jpegBytes,
            ];
        }

        foreach ($mrnOnlyOrdinals as $ordinal) {
            $patientId = 2000 + $ordinal;
            $dateUtc = $beirutToday->subDays((($ordinal - 1) % 28) + 3)->setTime(16, 25)->utc()->format('Y-m-d H:i:s');

            $records[] = [
                'AdmNb' => null,
                'MRN' => $patientId,
                'Date' => $dateUtc,
                'Document' => $jpegBytes,
                'Tump' => $jpegBytes,
            ];
        }

        return $records;
    }

    private function seedLegacyDocuments($archive, array $records): void
    {
        foreach ($records as $record) {
            $exists = $archive->table('TblDocuments')
                ->where('MRN', $record['MRN'])
                ->where('Date', $record['Date'])
                ->when(
                    $record['AdmNb'] === null,
                    fn ($query) => $query->whereNull('AdmNb'),
                    fn ($query) => $query->where('AdmNb', $record['AdmNb'])
                )
                ->exists();

            if (!$exists) {
                $admNbSql = $record['AdmNb'] === null ? 'NULL' : (string) (int) $record['AdmNb'];
                $mrnSql = (string) (int) $record['MRN'];
                $dateSql = "'" . str_replace("'", "''", (string) $record['Date']) . "'";
                $documentHex = strtoupper(bin2hex((string) $record['Document']));
                $tumpHex = strtoupper(bin2hex((string) $record['Tump']));

                $archive->unprepared(
                    "INSERT INTO [TblDocuments] ([AdmNb], [MRN], [Date], [Document], [Tump]) VALUES ({$admNbSql}, {$mrnSql}, {$dateSql}, 0x{$documentHex}, 0x{$tumpHex})"
                );
            }
        }
    }

    private function seedUsers(): void
    {
        $doctorRole = Role::query()->where('name', 'doctor')->firstOrFail();
        $nurseRole = Role::query()->where('name', 'nurse')->firstOrFail();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        foreach (range(1001, 1024) as $doctorId) {
            $user = User::query()->updateOrCreate(
                ['username' => sprintf('seed_doctor_%d', $doctorId)],
                [
                    'display_name' => sprintf('Seed Doctor %d', $doctorId),
                    'email' => sprintf('seed_doctor_%d@example.com', $doctorId),
                    'password' => self::PASSWORD_HASH,
                    'is_active' => !in_array($doctorId, [1007, 1018], true),
                    'doctor_id' => $doctorId,
                ]
            );

            $user->syncRoles([$doctorRole->name]);
            $user->syncPermissions([]);
        }

        foreach (range(1, 16) as $index) {
            $user = User::query()->updateOrCreate(
                ['username' => sprintf('seed_nurse_%02d', $index)],
                [
                    'display_name' => sprintf('Seed Nurse %02d', $index),
                    'email' => sprintf('seed_nurse_%02d@example.com', $index),
                    'password' => self::PASSWORD_HASH,
                    'is_active' => !in_array($index, [6, 14], true),
                    'doctor_id' => null,
                ]
            );

            $user->syncRoles([$nurseRole->name]);
            $user->syncPermissions([]);
        }

        foreach (range(1, 6) as $index) {
            $user = User::query()->updateOrCreate(
                ['username' => sprintf('seed_admin_%02d', $index)],
                [
                    'display_name' => sprintf('Seed Admin %02d', $index),
                    'email' => sprintf('seed_admin_%02d@example.com', $index),
                    'password' => self::PASSWORD_HASH,
                    'is_active' => $index !== 6,
                    'doctor_id' => null,
                ]
            );

            $user->syncRoles([$adminRole->name]);
            $user->syncPermissions([]);
        }
    }

    private function seedDigitalForms(CarbonImmutable $beirutToday, array $doctorAssignments): void
    {
        $formOrdinals = range(13, 34);

        foreach ($formOrdinals as $ordinal) {
            $admissionId = 3000 + $ordinal;
            $doctorId = $doctorAssignments[$ordinal];
            $doctorUserId = User::query()->where('doctor_id', $doctorId)->value('id')
                ?? User::query()->where('username', 'admin1')->value('id');

            $payload = $this->buildFormPayload($ordinal, $beirutToday, $doctorId);
            $strokes = $ordinal <= 17 ? $this->buildSeedStrokes() : [];

            DigitalAdmissionForm::query()->updateOrCreate(
                ['AdmissionId' => $admissionId],
                [
                    'DoctorId' => $doctorId,
                    'UpdatedByUserId' => $doctorUserId,
                    'Payload' => $payload,
                    'Strokes' => $strokes,
                    'FormVersion' => self::FORM_VERSION,
                    'Status' => $ordinal % 3 === 0 ? 'submitted' : 'draft',
                ]
            );
        }
    }

    private function buildFormPayload(int $ordinal, CarbonImmutable $beirutToday, int $doctorId): array
    {
        $isChild = in_array($ordinal, [21, 22, 33, 34], true);
        $patientNumber = 2000 + $ordinal;
        $admissionLocalDate = $beirutToday->subDays(($ordinal - 1) % 28)->format('d/m/Y');

        return [
            'patientName' => sprintf('Patient %02d', $ordinal),
            'fileNumber' => (string) $patientNumber,
            'vitals' => $isChild ? 'Stable child vitals' : 'Stable adult vitals',
            'currentMedication' => $isChild ? 'Pediatric supplements' : 'Maintenance medication',
            'chiefComplaint' => $isChild ? 'Routine pediatric follow-up' : sprintf('Chief complaint note %02d', $ordinal),
            'physicalExam' => sprintf('Physical exam note %02d', $ordinal),
            'management' => sprintf('Management plan %02d', $ordinal),
            'doctorNote' => sprintf('Doctor note %02d', $ordinal),
            'doctorName' => $doctorId === 1 ? 'Dr. Alaa Moussa' : sprintf('Seed Doctor %d', $doctorId),
            'stampAndSign' => 'Signed',
            'date' => $admissionLocalDate,
            'TEM' => $isChild ? '37.0' : '36.8',
            'BP' => $isChild ? '' : sprintf('%d/%d', 110 + ($ordinal % 10), 70 + ($ordinal % 8)),
            'HR' => (string) (72 + ($ordinal % 18)),
            'SPO2' => '98',
            'HEIGHT' => $isChild ? '78' : (string) (160 + ($ordinal % 20)),
            'WEIGHT' => $isChild ? (string) (9 + ($ordinal % 4)) : (string) (58 + ($ordinal % 16)),
            'PREG' => $ordinal % 9 === 0 ? 'Possible' : '',
            'MUAC' => $isChild ? (string) (13 + ($ordinal % 2)) : '',
            'zScore' => $isChild ? '-1.0' : '',
            'HC' => $isChild ? (string) (44 + ($ordinal % 3)) : '',
            'RR' => $isChild ? (string) (24 + ($ordinal % 5)) : '18',
            'phoneNumber' => sprintf('70%06d', $ordinal),
            'remarks' => $isChild ? sprintf('Child remarks %02d', $ordinal) : sprintf('Adult remarks %02d', $ordinal),
            'diagnosisICD' => sprintf('R%02d', $ordinal),
        ];
    }

    private function buildSeedStrokes(): array
    {
        return [[
            'id' => 'seed-stroke-1',
            'tool' => 'pen',
            'width' => 3,
            'color' => '#38bdf8',
            'points' => [
                ['x' => 12, 'y' => 18, 'timestamp' => 1],
                ['x' => 56, 'y' => 22, 'timestamp' => 2],
                ['x' => 84, 'y' => 40, 'timestamp' => 3],
            ],
        ]];
    }

    private function seedAttachments(CarbonImmutable $beirutToday, array $doctorAssignments): void
    {
        $attachmentOrdinals = [29, 30, 31, 33, 34, 35, 37, 38, 39, 41];
        $uploaderId = User::query()->where('username', 'seed_nurse_01')->value('id')
            ?? User::query()->where('username', 'admin1')->value('id');
        $jpegBytes = base64_decode(self::TINY_JPEG_BASE64);

        foreach ($attachmentOrdinals as $position => $ordinal) {
            $variants = $position < 8 ? [1, 2] : [1];

            foreach ($variants as $variant) {
                $path = sprintf('admissions/seed/admission-%04d-%02d.jpg', 3000 + $ordinal, $variant);
                if (!Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->put($path, $jpegBytes);
                }

                AdmissionAttachment::query()->updateOrCreate(
                    ['Path' => $path],
                    [
                        'DoctorId' => $doctorAssignments[$ordinal],
                        'UploadedByUserId' => $uploaderId,
                        'AdmissionId' => 3000 + $ordinal,
                        'Mime' => 'image/jpeg',
                        'Label' => sprintf('Seed attachment %02d-%d', $ordinal, $variant),
                        'UploadedAt' => $beirutToday
                            ->subDays(($ordinal - 1) % 28)
                            ->setTime(10 + $variant, 15)
                            ->utc()
                            ->format('Y-m-d H:i:s'),
                    ]
                );
            }
        }
    }
}
