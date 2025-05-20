-- Insert stores
INSERT INTO stores (name) VALUES
('Store A'),
('Store B'),
('Store C');

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO users (username, password, role) VALUES
('admin', 'admin123', 'admin');

-- Insert products
INSERT INTO products (name, description) VALUES
('Laptop', 'High-performance laptop'),
('Smartphone', 'Latest model smartphone'),
('Headphones', 'Wireless noise-cancelling headphones'),
('Tablet', '10-inch tablet with stylus'),
('Smartwatch', 'Fitness tracking smartwatch');

-- Insert inventory
INSERT INTO inventory (product_id, store_id, quantity) VALUES
(1, 1, 15),
(2, 1, 20),
(3, 2, 25),
(4, 2, 10),
(5, 3, 30); 