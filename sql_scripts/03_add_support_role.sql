-- PURPOSE: Add the optional 'Support' role to the users table ENUM.
-- Run this script ONCE against the live database after 01_schema.sql + 02_seed.sql.
-- DO NOT re-run 01_schema.sql after this — it will drop and recreate the table, losing data.
--
-- After running this script, also update public/admin/edit_user.php:
--   Change: $allowed_roles = ['Viewer', 'Creator', 'Admin'];
--   To:     $allowed_roles = ['Viewer', 'Creator', 'Admin', 'Support'];

USE movie_review_db;

ALTER TABLE dbProj_users
    MODIFY COLUMN role ENUM('Viewer', 'Creator', 'Admin', 'Support') DEFAULT 'Viewer';
