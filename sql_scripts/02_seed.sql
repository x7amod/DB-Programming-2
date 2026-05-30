-- PURPOSE: Seed data for the Movie Review System.
-- Includes test users, categories, movies, and reviews.

USE movie_review_db;

-- 1. Seed Categories
INSERT INTO dbProj_categories (id, name, createdby) VALUES 
(UUID(), 'Action', NULL),
(UUID(), 'Drama', NULL),
(UUID(), 'Sci-Fi', NULL);

-- Store IDs for reference in later inserts (using subqueries or variables is cleaner)
SET @action_id = (SELECT id FROM dbProj_categories WHERE name = 'Action');
SET @drama_id = (SELECT id FROM dbProj_categories WHERE name = 'Drama');
SET @scifi_id = (SELECT id FROM dbProj_categories WHERE name = 'Sci-Fi');

-- 2. Seed Users (Roles: Viewer, Creator, Admin)
-- Passwords are 'password' (hashed using a generic placeholder for now)
INSERT INTO dbProj_users (id, username, email, password_hash, role) VALUES 
(UUID(), 'admin_user', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'),
(UUID(), 'creator_ahmed', 'ahmed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Creator'),
(UUID(), 'viewer_abbas', 'abbas@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Viewer');

SET @admin_id = (SELECT id FROM dbProj_users WHERE role = 'Admin');
SET @creator_id = (SELECT id FROM dbProj_users WHERE username = 'creator_ahmed');
SET @viewer_id = (SELECT id FROM dbProj_users WHERE username = 'viewer_abbas');

-- 3. Seed Movies (15 Records Total)
INSERT INTO dbProj_movies (id, title, description, category_id, creator_id, is_published, view_count, createdby) VALUES 
-- Action (5)
(UUID(), 'The Dark Knight', 'Batman vs Joker in Gotham.', @action_id, @creator_id, TRUE, 500, @creator_id),
(UUID(), 'Mad Max: Fury Road', 'A post-apocalyptic chase.', @action_id, @creator_id, TRUE, 300, @creator_id),
(UUID(), 'John Wick', 'Retired hitman seeks revenge.', @action_id, @creator_id, TRUE, 450, @creator_id),
(UUID(), 'Gladiator', 'A general becomes a slave.', @action_id, @creator_id, TRUE, 200, @creator_id),
(UUID(), 'Die Hard', 'Cop saves Christmas.', @action_id, @creator_id, TRUE, 150, @creator_id),
-- Drama (5)
(UUID(), 'The Godfather', 'Crime family saga.', @drama_id, @creator_id, TRUE, 600, @creator_id),
(UUID(), 'The Shawshank Redemption', 'Hope in prison.', @drama_id, @creator_id, TRUE, 700, @creator_id),
(UUID(), 'Schindlers List', 'Saving lives during WWII.', @drama_id, @creator_id, TRUE, 400, @creator_id),
(UUID(), 'Parasite', 'Class conflict in Korea.', @drama_id, @creator_id, TRUE, 550, @creator_id),
(UUID(), 'Forrest Gump', 'Life is like a box of chocolates.', @drama_id, @creator_id, TRUE, 480, @creator_id),
-- Sci-Fi (5)
(UUID(), 'Inception', 'Dreams within dreams.', @scifi_id, @creator_id, TRUE, 650, @creator_id),
(UUID(), 'The Matrix', 'Reality is a simulation.', @scifi_id, @creator_id, TRUE, 620, @creator_id),
(UUID(), 'Interstellar', 'Space travel to save humanity.', @scifi_id, @creator_id, TRUE, 580, @creator_id),
(UUID(), 'Blade Runner 2049', 'A search for a lost replicant.', @scifi_id, @creator_id, TRUE, 320, @creator_id),
(UUID(), 'Arrival', 'Linguistics and aliens.', @scifi_id, @creator_id, TRUE, 290, @creator_id);

-- 4. Seed Reviews (Sample reviews for various movies)
INSERT INTO dbProj_reviews (id, movie_id, user_id, rating, comment, createdby) 
SELECT UUID(), id, @viewer_id, 5, 'Absolutely amazing!', @viewer_id FROM dbProj_movies LIMIT 5;

INSERT INTO dbProj_reviews (id, movie_id, user_id, rating, comment, createdby) 
SELECT UUID(), id, @admin_id, 4, 'Great production quality.', @admin_id FROM dbProj_movies ORDER BY title DESC LIMIT 5;
