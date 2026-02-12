-- SQL Server Troubleshooting Script
-- Run this in SSMS to check your SQL Server configuration

-- 1. Check if SQL Server is listening and what port it's using
SELECT 
    local_net_address,
    local_tcp_port,
    type_desc,
    state_desc
FROM sys.dm_exec_connections 
WHERE session_id = @@SPID;

-- 2. Check SQL Server instance name
SELECT @@SERVERNAME AS 'Server Name',
       @@SERVICENAME AS 'Service Name';

-- 3. Check if TCP/IP is enabled
EXEC xp_readerrorlog 0, 1, N'Server is listening on';

-- 4. Check SQL Server Browser status
-- (Run this in a new query window)
-- SELECT * FROM sys.dm_server_services WHERE servicename LIKE '%Browser%';

-- 5. Find the actual port SQL Server Express is using
-- Open SQL Server Configuration Manager:
-- 1. Start -> SQL Server Configuration Manager
-- 2. SQL Server Network Configuration -> Protocols for SQLEXPRESS
-- 3. TCP/IP -> Properties -> IP Addresses tab
-- 4. Look for "TCP Dynamic Ports" or "TCP Port" under IPAll
