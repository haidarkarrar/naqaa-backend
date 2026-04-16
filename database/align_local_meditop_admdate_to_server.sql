-- Local/dev only: align a local MEDITOP clone to the server schema.
-- Do NOT run this against the server machine. The server is the source of truth.
-- Expected server truth: dbo.TblAdmFiles.AdmDate is SQL Server datetime NULL.

USE [MEDITOP];
GO

PRINT 'Current dbo.TblAdmFiles.AdmDate definition';
SELECT
    c.TABLE_SCHEMA,
    c.TABLE_NAME,
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.CHARACTER_MAXIMUM_LENGTH,
    c.DATETIME_PRECISION,
    c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_SCHEMA = 'dbo'
  AND c.TABLE_NAME = 'TblAdmFiles'
  AND c.COLUMN_NAME = 'AdmDate';
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.TblAdmFiles')
      AND name = 'IX_TblAdmFiles_AdmDate'
)
BEGIN
    PRINT 'Dropping IX_TblAdmFiles_AdmDate before altering AdmDate';
    DROP INDEX IX_TblAdmFiles_AdmDate ON dbo.TblAdmFiles;
END;
GO

PRINT 'Aligning dbo.TblAdmFiles.AdmDate to datetime NULL to match the server schema';
ALTER TABLE dbo.TblAdmFiles
ALTER COLUMN AdmDate datetime NULL;
GO

PRINT 'Recreating IX_TblAdmFiles_AdmDate after altering AdmDate';
CREATE NONCLUSTERED INDEX IX_TblAdmFiles_AdmDate
ON dbo.TblAdmFiles (AdmDate);
GO

PRINT 'Updated dbo.TblAdmFiles.AdmDate definition';
SELECT
    c.TABLE_SCHEMA,
    c.TABLE_NAME,
    c.COLUMN_NAME,
    c.DATA_TYPE,
    c.CHARACTER_MAXIMUM_LENGTH,
    c.DATETIME_PRECISION,
    c.IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS c
WHERE c.TABLE_SCHEMA = 'dbo'
  AND c.TABLE_NAME = 'TblAdmFiles'
  AND c.COLUMN_NAME = 'AdmDate';
GO
