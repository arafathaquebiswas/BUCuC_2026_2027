-- Three-Table Migration Script
-- Run this in phpMyAdmin or MySQL command line

-- Step 1: Create pending_applications table
CREATE TABLE IF NOT EXISTS pending_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    university_id VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    gsuite_email VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    facebook_url TEXT,
    firstPriority VARCHAR(100) NOT NULL,
    secondPriority VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Create shortlisted_members table
CREATE TABLE IF NOT EXISTS shortlisted_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    university_id VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    gsuite_email VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    facebook_url TEXT,
    firstPriority VARCHAR(100) NOT NULL,
    secondPriority VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 3: Migrate data from members table
-- Copy New_member status to pending_applications
INSERT INTO pending_applications 
(full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at)
SELECT full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at
FROM members 
WHERE membership_status = 'New_member';

-- Copy Shortlisted status to shortlisted_members
INSERT INTO shortlisted_members 
(full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at)
SELECT full_name, university_id, email, gsuite_email, department, phone, semester, gender, facebook_url, firstPriority, secondPriority, created_at, updated_at
FROM members 
WHERE membership_status = 'Shortlisted';

-- Step 4: Clean up members table
-- Remove pending and shortlisted from members table
DELETE FROM members WHERE membership_status != 'Accepted';

-- Remove membership_status column from members table
ALTER TABLE members DROP COLUMN membership_status;

-- Verification queries (run these to check migration)
-- SELECT COUNT(*) as pending_count FROM pending_applications;
-- SELECT COUNT(*) as shortlisted_count FROM shortlisted_members;
-- SELECT COUNT(*) as members_count FROM members;
