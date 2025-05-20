-- Drop existing unique constraint on barcode
ALTER TABLE products DROP INDEX barcode;

-- Add size column if it doesn't exist
ALTER TABLE products ADD COLUMN IF NOT EXISTS size VARCHAR(50) NOT NULL DEFAULT '';

-- Add composite unique index for barcode and size
ALTER TABLE products ADD UNIQUE INDEX barcode_size_idx (barcode, size);

-- Update price field type if needed
ALTER TABLE products MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
