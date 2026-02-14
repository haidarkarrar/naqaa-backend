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
        $hasExplicitId = isset($data[0][$keyColumn]);
        $needsIdentityInsert = false;

        // Check if the column has IDENTITY property (only for SQL Server)
        if ($driver === 'sqlsrv' && $hasExplicitId) {
            try {
                // Try different table name formats
                $tableVariations = [
                    "dbo.[{$table}]",
                    "[dbo].[{$table}]",
                    "{$table}",
                    "[{$table}]",
                ];
                
                $result = null;
                foreach ($tableVariations as $tableName) {
                    $result = $connection->selectOne("
                        SELECT is_identity 
                        FROM sys.columns 
                        WHERE object_id = OBJECT_ID(?) 
                        AND name = ?
                    ", [$tableName, $keyColumn]);
                    
                    if ($result !== null) {
                        break;
                    }
                }
                
                $needsIdentityInsert = $result && isset($result->is_identity) && $result->is_identity == 1;
            } catch (\Exception $e) {
                // If check fails, assume no IDENTITY and proceed without IDENTITY_INSERT
                // This handles cases where table doesn't exist or column doesn't have IDENTITY
                $needsIdentityInsert = false;
            }
        }

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
        $naqaa = DB::connection('naqaa');

        // Seed naqaa database tables only
        // Add your naqaa table seeding here if needed
        // Example:
        // $this->insertIfNotExists($naqaa, 'some_naqaa_table', [
        //     ['Id' => 1, 'Name' => 'Example'],
        // ]);
    }
}
