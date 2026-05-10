-- PURPOSE: Schema definitions for the Movie Review System.
-- PREFIX: All tables use the 'dbProj_' prefix.
-- DESIGN: No physical deletions allowed; uses 'inactive' flag for soft-deletes.

CREATE DATABASE IF NOT EXISTS movie_review_db;
USE movie_review_db;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Categories Table
DROP TABLE IF EXISTS dbProj_categories;
CREATE TABLE dbProj_categories (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(150),
    -- System Fields
    inactive BOOLEAN DEFAULT FALSE,
    createdon DATETIME DEFAULT CURRENT_TIMESTAMP,
    createdby CHAR(36),
    modifiedon DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modifiedby CHAR(36)
) ENGINE=InnoDB;

-- 2. Users Table
DROP TABLE IF EXISTS dbProj_users;
CREATE TABLE dbProj_users (
    id CHAR(36) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Viewer', 'Creator', 'Admin') DEFAULT 'Viewer',
    -- System Fields
    inactive BOOLEAN DEFAULT FALSE,
    createdon DATETIME DEFAULT CURRENT_TIMESTAMP,
    createdby CHAR(36),
    modifiedon DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modifiedby CHAR(36)
) ENGINE=InnoDB;

-- 3. Movies Table
DROP TABLE IF EXISTS dbProj_movies;
CREATE TABLE dbProj_movies (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    media_url VARCHAR(255),
    category_id CHAR(36),
    creator_id CHAR(36),
    is_published BOOLEAN DEFAULT FALSE,
    view_count INT DEFAULT 0,
    -- System Fields
    inactive BOOLEAN DEFAULT FALSE,
    createdon DATETIME DEFAULT CURRENT_TIMESTAMP,
    createdby CHAR(36),
    modifiedon DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modifiedby CHAR(36),
    FOREIGN KEY (category_id) REFERENCES dbProj_categories(id),
    FOREIGN KEY (creator_id) REFERENCES dbProj_users(id),
    FULLTEXT(title, description)
) ENGINE=InnoDB;

-- 4. Reviews Table
DROP TABLE IF EXISTS dbProj_reviews;
CREATE TABLE dbProj_reviews (
    id CHAR(36) PRIMARY KEY,
    movie_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    rating TINYINT CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    -- System Fields
    inactive BOOLEAN DEFAULT FALSE,
    createdon DATETIME DEFAULT CURRENT_TIMESTAMP,
    createdby CHAR(36),
    modifiedon DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modifiedby CHAR(36),
    FOREIGN KEY (movie_id) REFERENCES dbProj_movies(id),
    FOREIGN KEY (user_id) REFERENCES dbProj_users(id)
) ENGINE=InnoDB;

-- 5. Deactivation Log Table
-- Requirement 1.6: Show system message when content is removed (deactivated)
DROP TABLE IF EXISTS dbProj_status_log;
CREATE TABLE dbProj_status_log (
    id CHAR(36) PRIMARY KEY,
    content_id CHAR(36),
    content_type VARCHAR(50),
    message TEXT,
    -- System Fields
    inactive BOOLEAN DEFAULT FALSE,
    createdon DATETIME DEFAULT CURRENT_TIMESTAMP,
    createdby CHAR(36),
    modifiedon DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modifiedby CHAR(36)
) ENGINE=InnoDB;

-- 6. ADVANCED FEATURE: Stored Procedure
-- Generates report for popular content (Task 1.7)
DELIMITER //
DROP PROCEDURE IF EXISTS GetPopularMoviesReport//
CREATE PROCEDURE GetPopularMoviesReport(IN startDate DATETIME, IN endDate DATETIME)
BEGIN
    SELECT 
        m.title, 
        m.view_count, 
        COUNT(r.id) as review_count, 
        AVG(r.rating) as avg_rating
    FROM dbProj_movies m
    LEFT JOIN dbProj_reviews r ON m.id = r.movie_id
    WHERE m.inactive = FALSE 
      AND m.createdon BETWEEN startDate AND endDate
    GROUP BY m.id
    ORDER BY m.view_count DESC;
END //
DELIMITER ;

-- 7. ADVANCED FEATURE: Trigger
-- Logs a message when a movie is marked as 'inactive' (soft-delete)
DELIMITER //
DROP TRIGGER IF EXISTS trg_movie_deactivation//
CREATE TRIGGER trg_movie_deactivation
BEFORE UPDATE ON dbProj_movies
FOR EACH ROW
BEGIN
    -- Check if inactive was changed from false to true
    IF OLD.inactive = FALSE AND NEW.inactive = TRUE THEN
        INSERT INTO dbProj_status_log (id, content_id, content_type, message, createdon, createdby)
        VALUES (UUID(), OLD.id, 'MOVIE', CONCAT('System Message: The movie "', OLD.title, '" has been deactivated by an administrator.'), NOW(), NEW.modifiedby);
    END IF;
END //
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
