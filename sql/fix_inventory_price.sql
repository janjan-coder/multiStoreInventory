-- First make sure the price column exists and has the correct type
ALTER TABLE inventory MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Add an index on the price column for better performance
ALTER TABLE inventory ADD INDEX idx_price (price);

-- Make sure inventory table uses the correct storage engine
ALTER TABLE inventory ENGINE = InnoDB;
