CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_material INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    status INT NOT NULL,
    created_at TIMESTAMP DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (id_material) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (status) REFERENCES status(id)
);

CREATE TABLE material_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_material INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    img VARCHAR(255) DEFAULT NULL,
    code VARCHAR(7) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (id_material) REFERENCES materials(id) ON DELETE CASCADE
);
