-- Drop the existing UNIQUE constraint on barcode
ALTER TABLE products DROP INDEX barcode;

-- Add size column if it doesn't exist
ALTER TABLE products ADD COLUMN IF NOT EXISTS size VARCHAR(50) NOT NULL DEFAULT '';

-- Add a new composite UNIQUE constraint on barcode and size
ALTER TABLE products ADD CONSTRAINT barcode_size_unique UNIQUE (barcode, size);
