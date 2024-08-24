USE rossi_equipamientos;

ALTER TABLE product_categories DROP FOREIGN KEY product_categories_ibfk_2;

DROP TABLE IF EXISTS products_categories CASCADE;

CREATE TABLE products_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_category INT DEFAULT NULL,
    category VARCHAR(255) NOT NULL,
    img VARCHAR(255) DEFAULT NULL,
    video VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT NULL,
    status INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_category) REFERENCES products_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (status) REFERENCES categories_status(id)
);
