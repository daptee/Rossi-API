USE rossi_equipamientos;

CREATE TABLE components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_component INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    img VARCHAR(255) NULL,
    description TEXT,
    status INT NOT NULL,
    id_category INT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (status) REFERENCES status(id),
    FOREIGN KEY (id_category) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (id_component) REFERENCES components(id) ON DELETE SET NULL
);