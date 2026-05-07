<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'naqaa';
    private string $table = 'TblAdmissionAttachments';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable($this->table)) {
            $this->createDesiredTable($this->table, true);
            return;
        }

        if ($this->tableMatchesDesiredShape()) {
            return;
        }

        $rowCount = (int) DB::connection($this->connection)->table($this->table)->count();

        if ($rowCount === 0) {
            $schema->drop($this->table);
            $this->createDesiredTable($this->table, true);
            return;
        }

        $temporaryTable = $this->table . '_tmp_id_repair';

        if ($schema->hasTable($temporaryTable)) {
            $schema->drop($temporaryTable);
        }

        $this->createDesiredTable($temporaryTable, false);
        $this->copyExistingRowsTo($temporaryTable);
        $schema->drop($this->table);
        DB::connection($this->connection)->statement("EXEC sp_rename '{$temporaryTable}', '{$this->table}'");
        $this->createUploadedByUserIdIndex($this->table);
    }

    public function down(): void
    {
        // Irreversible: this migration repairs a drifted live SQL Server table to the expected schema.
    }

    private function tableMatchesDesiredShape(): bool
    {
        $columns = collect(DB::connection($this->connection)->select("
            SELECT c.name AS column_name, c.is_identity
            FROM sys.columns c
            INNER JOIN sys.objects o ON c.object_id = o.object_id
            WHERE o.name = ?
            ORDER BY c.column_id
        ", [$this->table]))->keyBy('column_name');

        if (!$columns->has('Id') || (int) $columns->get('Id')->is_identity !== 1 || $columns->has('id')) {
            return false;
        }

        $primaryKey = DB::connection($this->connection)->selectOne("
            SELECT TOP 1 c.name AS column_name
            FROM sys.indexes i
            INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
            INNER JOIN sys.objects o ON i.object_id = o.object_id
            WHERE o.name = ? AND i.is_primary_key = 1
            ORDER BY ic.key_ordinal
        ", [$this->table]);

        return $primaryKey?->column_name === 'Id';
    }

    private function createDesiredTable(string $tableName, bool $withUploadedByUserIndex): void
    {
        Schema::connection($this->connection)->create($tableName, function (Blueprint $table) use ($withUploadedByUserIndex) {
            $table->increments('Id');
            $table->unsignedBigInteger('DoctorId')->nullable();
            $table->unsignedBigInteger('AdmissionId');
            $table->string('Path');
            $table->string('Mime', 64);
            $table->string('Label')->nullable();
            $table->dateTime('UploadedAt');
            $table->timestamps();
            $table->unsignedBigInteger('UploadedByUserId')->nullable();

            if ($withUploadedByUserIndex) {
                $table->index('UploadedByUserId', 'tbladmissionattachments_uploadedbyuserid_index');
            }
        });
    }

    private function createUploadedByUserIdIndex(string $tableName): void
    {
        DB::connection($this->connection)->unprepared("
            CREATE INDEX [tbladmissionattachments_uploadedbyuserid_index]
            ON [{$tableName}] ([UploadedByUserId])
        ");
    }

    private function copyExistingRowsTo(string $temporaryTable): void
    {
        $columns = collect(DB::connection($this->connection)->select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
        ", [$this->table]))->pluck('COLUMN_NAME')->all();

        $sourceIdColumn = in_array('Id', $columns, true) ? 'Id' : 'id';

        $selectColumn = static fn (string $column, string $fallback = 'NULL') => in_array($column, $columns, true)
            ? "[{$column}]"
            : "{$fallback}";

        $doctorIdSelect = $selectColumn('DoctorId');
        $admissionIdSelect = $selectColumn('AdmissionId', '0');
        $pathSelect = $selectColumn('Path', "N''");
        $mimeSelect = $selectColumn('Mime', "N''");
        $labelSelect = $selectColumn('Label');
        $uploadedAtSelect = $selectColumn('UploadedAt', 'GETDATE()');
        $createdAtSelect = $selectColumn('created_at');
        $updatedAtSelect = $selectColumn('updated_at');
        $uploadedByUserIdSelect = $selectColumn('UploadedByUserId');

        DB::connection($this->connection)->unprepared("
            SET IDENTITY_INSERT [{$temporaryTable}] ON;

            INSERT INTO [{$temporaryTable}] (
                [Id],
                [DoctorId],
                [AdmissionId],
                [Path],
                [Mime],
                [Label],
                [UploadedAt],
                [created_at],
                [updated_at],
                [UploadedByUserId]
            )
            SELECT
                [{$sourceIdColumn}] AS [Id],
                {$doctorIdSelect},
                {$admissionIdSelect},
                {$pathSelect},
                {$mimeSelect},
                {$labelSelect},
                {$uploadedAtSelect},
                {$createdAtSelect},
                {$updatedAtSelect},
                {$uploadedByUserIdSelect}
            FROM [{$this->table}];

            SET IDENTITY_INSERT [{$temporaryTable}] OFF;
        ");
    }
};
