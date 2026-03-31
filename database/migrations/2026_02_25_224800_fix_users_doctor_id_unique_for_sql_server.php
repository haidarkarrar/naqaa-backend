<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('naqaa');

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $connection->statement("
            IF EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_doctor_id_unique'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            DROP INDEX [users_doctor_id_unique] ON [dbo].[users];
        ");

        $connection->statement("
            IF NOT EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_doctor_id_unique_not_null'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            CREATE UNIQUE INDEX [users_doctor_id_unique_not_null]
                ON [dbo].[users] ([doctor_id])
                WHERE [doctor_id] IS NOT NULL;
        ");
    }

    public function down(): void
    {
        $connection = DB::connection('naqaa');

        if ($connection->getDriverName() !== 'sqlsrv') {
            return;
        }

        $connection->statement("
            IF EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_doctor_id_unique_not_null'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            DROP INDEX [users_doctor_id_unique_not_null] ON [dbo].[users];
        ");

        $connection->statement("
            IF NOT EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_doctor_id_unique'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            CREATE UNIQUE INDEX [users_doctor_id_unique] ON [dbo].[users] ([doctor_id]);
        ");
    }
};

