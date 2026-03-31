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
                WHERE name = 'users_email_unique'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            DROP INDEX [users_email_unique] ON [dbo].[users];
        ");

        $connection->statement("
            IF NOT EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_email_unique_not_null'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            CREATE UNIQUE INDEX [users_email_unique_not_null]
                ON [dbo].[users] ([email])
                WHERE [email] IS NOT NULL;
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
                WHERE name = 'users_email_unique_not_null'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            DROP INDEX [users_email_unique_not_null] ON [dbo].[users];
        ");

        $connection->statement("
            IF NOT EXISTS (
                SELECT 1
                FROM sys.indexes
                WHERE name = 'users_email_unique'
                  AND object_id = OBJECT_ID(N'[dbo].[users]')
            )
            CREATE UNIQUE INDEX [users_email_unique] ON [dbo].[users] ([email]);
        ");
    }
};

