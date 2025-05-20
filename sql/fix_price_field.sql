-- Make sure price field exists and has correct type
ALTER TABLE inventory MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
