-- Diagnose MEDITOP.dbo.TblAdmFiles.AdmDate with the server schema as the source of truth.
-- Expected server truth: dbo.TblAdmFiles.AdmDate is SQL Server datetime.
-- Run the whole script in SSMS against the MEDITOP database.

USE [MEDITOP];
GO

DECLARE @StartAt DATETIME = '2016-04-15 21:00:00.000';
DECLARE @EndBefore DATETIME = '2026-04-16 21:00:00.000';

PRINT '1) SQL Server and database basics';
SELECT
    @@SERVERNAME AS server_name,
    @@SERVICENAME AS service_name,
    DB_NAME() AS database_name,
    SERVERPROPERTY('Collation') AS server_collation,
    DATABASEPROPERTYEX(DB_NAME(), 'Collation') AS database_collation;

PRINT '2) Exact TblAdmFiles.AdmDate column definition';
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

PRINT '3) sys.columns / sys.types view of TblAdmFiles.AdmDate';
SELECT
    t.name AS table_name,
    c.name AS column_name,
    ty.name AS sql_type,
    c.max_length,
    c.precision,
    c.scale,
    c.is_nullable
FROM sys.columns c
INNER JOIN sys.tables t
    ON t.object_id = c.object_id
INNER JOIN sys.types ty
    ON ty.user_type_id = c.user_type_id
WHERE t.name = 'TblAdmFiles'
  AND c.name = 'AdmDate';

PRINT '4) Sample values and conversion check';
SELECT TOP (50)
    AdmDate,
    TRY_CONVERT(datetime, AdmDate) AS adm_date_as_datetime
FROM dbo.TblAdmFiles
ORDER BY Id DESC;

PRINT '5) Count problematic AdmDate values';
SELECT
    COUNT(*) AS total_rows,
    SUM(CASE WHEN AdmDate IS NULL THEN 1 ELSE 0 END) AS null_rows,
    SUM(CASE WHEN LTRIM(RTRIM(CONVERT(nvarchar(255), AdmDate))) = '' THEN 1 ELSE 0 END) AS blank_rows,
    SUM(
        CASE
            WHEN AdmDate IS NOT NULL
             AND TRY_CONVERT(datetime, AdmDate) IS NULL
            THEN 1
            ELSE 0
        END
    ) AS unconvertible_rows
FROM dbo.TblAdmFiles;

PRINT '6) Show unconvertible AdmDate rows, if any';
SELECT TOP (100)
    Id,
    AdmDate
FROM dbo.TblAdmFiles
WHERE AdmDate IS NOT NULL
  AND TRY_CONVERT(datetime, AdmDate) IS NULL
ORDER BY Id DESC;

PRINT '7) Direct reproduction of the API date filter';
SELECT COUNT(*) AS matching_rows
FROM dbo.TblAdmFiles a
WHERE EXISTS (
    SELECT 1
    FROM dbo.tblWorks w
    WHERE w.AdmId = a.Id
      AND w.DoctorId IS NOT NULL
)
AND a.AdmDate >= @StartAt
AND a.AdmDate < @EndBefore;

PRINT '8) Safer conversion-based comparison for diagnosis only';
SELECT COUNT(*) AS matching_rows_via_try_convert
FROM dbo.TblAdmFiles a
WHERE EXISTS (
    SELECT 1
    FROM dbo.tblWorks w
    WHERE w.AdmId = a.Id
      AND w.DoctorId IS NOT NULL
)
AND TRY_CONVERT(datetime, a.AdmDate) >= @StartAt
AND TRY_CONVERT(datetime, a.AdmDate) < @EndBefore;

PRINT '9) Sample matching rows via conversion-based comparison';
SELECT TOP (25)
    a.Id,
    a.PatientId,
    a.DoctorId,
    a.AdmDate,
    TRY_CONVERT(datetime, a.AdmDate) AS adm_date_as_datetime,
    a.Closed
FROM dbo.TblAdmFiles a
WHERE EXISTS (
    SELECT 1
    FROM dbo.tblWorks w
    WHERE w.AdmId = a.Id
      AND w.DoctorId IS NOT NULL
)
AND TRY_CONVERT(datetime, a.AdmDate) >= @StartAt
AND TRY_CONVERT(datetime, a.AdmDate) < @EndBefore
ORDER BY TRY_CONVERT(datetime, a.AdmDate) DESC;
