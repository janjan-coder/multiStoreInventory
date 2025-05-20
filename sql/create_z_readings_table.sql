-- Create z_readings table
CREATE TABLE IF NOT EXISTS z_readings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    store_id INT NOT NULL,
    cashier_id INT NOT NULL,
    reading_year VARCHAR(4) NOT NULL,
    total_transactions INT NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    cash_sales DECIMAL(10,2) NOT NULL,
    card_sales DECIMAL(10,2) NOT NULL,
    gcash_sales DECIMAL(10,2) NOT NULL,
    business_days INT NOT NULL,
    business_months INT NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (cashier_id) REFERENCES users(id)
);
