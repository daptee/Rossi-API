USE u290214683_rossi;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS categories;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_category INT DEFAULT NULL,
    category VARCHAR(255) NOT NULL,
    img VARCHAR(255) DEFAULT NULL,
    sub_img VARCHAR(255) DEFAULT NULL,
    video VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT NULL,
    status INT NOT NULL,
    grid JSON DEFAULT NULL,
    meta_data JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_category) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (status) REFERENCES status(id)
);