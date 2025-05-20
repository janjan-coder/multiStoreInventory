-- Add price field to inventory table if it doesn't exist
ALTER TABLE inventory ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
