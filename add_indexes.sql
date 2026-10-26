-- Add indexes to the user table
ALTER TABLE user ADD PRIMARY KEY (user_id);
CREATE UNIQUE INDEX idx_username ON user(username);
CREATE UNIQUE INDEX idx_email ON user(email);
CREATE INDEX idx_role_id ON user(role_id);

-- Verify indexes
SHOW INDEXES FROM user;

-- Test queries with EXPLAIN to verify index usage
EXPLAIN SELECT * FROM user WHERE username = 'testuser';
EXPLAIN SELECT * FROM user WHERE email = 'test@example.com';
EXPLAIN SELECT u.*, r.role_name 
FROM user u 
JOIN userrole r ON u.role_id = r.role_id;

-- Performance comparison queries
-- Query 1: Without index (if indexes are dropped)
-- SELECT * FROM user WHERE username = 'testuser';

-- Query 2: With index
-- SELECT * FROM user WHERE username = 'testuser';

-- Note: The actual performance improvement can be measured by comparing
-- the execution time of these queries before and after adding indexes 