USE bucuc;

-- Add date_of_birth to pending_applications
ALTER TABLE pending_applications ADD COLUMN date_of_birth DATE AFTER gender;

-- Add date_of_birth to shortlisted_members
ALTER TABLE shortlisted_members ADD COLUMN date_of_birth DATE AFTER gender;
