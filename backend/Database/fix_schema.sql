-- Fix AUTO_INCREMENT for tables that missing it
USE bucuc;

-- Fix AUTO_INCREMENT for pending_applications
ALTER TABLE pending_applications MODIFY id INT AUTO_INCREMENT;

-- Fix AUTO_INCREMENT for shortlisted_members
ALTER TABLE shortlisted_members MODIFY id INT AUTO_INCREMENT;

-- Fix AUTO_INCREMENT for members
ALTER TABLE members MODIFY id INT AUTO_INCREMENT;

-- Fix AUTO_INCREMENT for venuinfo
ALTER TABLE venuinfo MODIFY venue_id INT AUTO_INCREMENT;
