-- SQL Server User Creation Script
-- Run this script in SQL Server Management Studio (SSMS)
-- This creates a SQL Server login user for your Laravel application

-- Step 1: Enable SQL Server Authentication (if not already enabled)
-- Right-click server -> Properties -> Security -> Select "SQL Server and Windows Authentication mode"
-- Then restart SQL Server service

-- Step 2: Create a SQL Server login
-- Replace 'your_password_here' with a strong password
IF NOT EXISTS (SELECT * FROM sys.server_principals WHERE name = 'naqaa_app_user')
BEGIN
    CREATE LOGIN [naqaa_app_user] 
    WITH PASSWORD = 'your_password_here',
         DEFAULT_DATABASE = [master],
         CHECK_EXPIRATION = OFF,
         CHECK_POLICY = OFF;
    PRINT 'Login naqaa_app_user created successfully.';
END
ELSE
BEGIN
    PRINT 'Login naqaa_app_user already exists.';
END
GO

-- Step 3: Grant permissions to Meditop database
USE [Meditop];
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'naqaa_app_user')
BEGIN
    CREATE USER [naqaa_app_user] FOR LOGIN [naqaa_app_user];
    ALTER ROLE [db_owner] ADD MEMBER [naqaa_app_user];
    PRINT 'User naqaa_app_user added to Meditop database with db_owner role.';
END
ELSE
BEGIN
    PRINT 'User naqaa_app_user already exists in Meditop database.';
END
GO

-- Step 4: Grant permissions to naqaa database
USE [naqaa];
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'naqaa_app_user')
BEGIN
    CREATE USER [naqaa_app_user] FOR LOGIN [naqaa_app_user];
    ALTER ROLE [db_owner] ADD MEMBER [naqaa_app_user];
    PRINT 'User naqaa_app_user added to naqaa database with db_owner role.';
END
ELSE
BEGIN
    PRINT 'User naqaa_app_user already exists in naqaa database.';
END
GO

-- Step 5: Grant permissions to Archive database
USE [Archive];
IF NOT EXISTS (SELECT * FROM sys.database_principals WHERE name = 'naqaa_app_user')
BEGIN
    CREATE USER [naqaa_app_user] FOR LOGIN [naqaa_app_user];
    ALTER ROLE [db_owner] ADD MEMBER [naqaa_app_user];
    PRINT 'User naqaa_app_user added to Archive database with db_owner role.';
END
ELSE
BEGIN
    PRINT 'User naqaa_app_user already exists in Archive database.';
END
GO

-- After running this script:
-- 1. Update your .env file with:
--    MEDITOP_DB_USERNAME=naqaa_app_user
--    MEDITOP_DB_PASSWORD=your_password_here
--    (same for NAQAA_DB_* and ARCHIVE_DB_*)
