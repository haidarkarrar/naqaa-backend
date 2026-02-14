-- SQL Script to insert Doctor Alaa into TblDoctors table
-- Run this in SQL Server Management Studio (SSMS) connected to the Meditop database

-- Check if doctor already exists
IF NOT EXISTS (SELECT 1 FROM TblDoctors WHERE Id = 1)
BEGIN
    -- Enable IDENTITY_INSERT to allow explicit Id value
    SET IDENTITY_INSERT TblDoctors ON;
    
    INSERT INTO TblDoctors (
        Id,
        FirstName,
        LastName,
        FullName,
        SpecialtyId,
        Username,
        Password,
        Approved,
        Radiologist
    )
    VALUES (
        1,                              -- Id
        'Alaa',                         -- FirstName
        'Moussa',                       -- LastName
        'Dr. Alaa Moussa',              -- FullName
        1,                              -- SpecialtyId (General Practice)
        'alaa',                         -- Username
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: 'password123' (bcrypt hash)
        1,                              -- Approved (bit: 1 = true)
        0                               -- Radiologist (bit: 0 = false)
    );
    
    -- Disable IDENTITY_INSERT
    SET IDENTITY_INSERT TblDoctors OFF;
    
    PRINT 'Doctor Alaa inserted successfully.';
END
ELSE
BEGIN
    PRINT 'Doctor with Id = 1 already exists.';
END
GO

-- Verify the insert
SELECT Id, FirstName, LastName, FullName, Username, Approved, Radiologist 
FROM TblDoctors 
WHERE Id = 1;
GO
