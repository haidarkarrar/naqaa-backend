-- SQL Server Database Setup Script
-- Run this script in SQL Server Management Studio (SSMS)
-- Make sure you're connected to your SQL Server instance

-- Create Meditop database
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'Meditop')
BEGIN
    CREATE DATABASE [Meditop]
    COLLATE SQL_Latin1_General_CP1_CI_AS;
    PRINT 'Database Meditop created successfully.';
END
ELSE
BEGIN
    PRINT 'Database Meditop already exists.';
END
GO

-- Create naqaa database
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'naqaa')
BEGIN
    CREATE DATABASE [naqaa]
    COLLATE SQL_Latin1_General_CP1_CI_AS;
    PRINT 'Database naqaa created successfully.';
END
ELSE
BEGIN
    PRINT 'Database naqaa already exists.';
END
GO

-- Create Archive database
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'Archive')
BEGIN
    CREATE DATABASE [Archive]
    COLLATE SQL_Latin1_General_CP1_CI_AS;
    PRINT 'Database Archive created successfully.';
END
ELSE
BEGIN
    PRINT 'Database Archive already exists.';
END
GO

-- Note: After creating databases, you may need to:
-- 1. Create SQL Server login/user for your application
-- 2. Grant appropriate permissions to the databases
-- 3. Update your .env file with connection details
