USE rossi_equipamientos;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attributes, attribute_values;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL
);

-- Insertar estados iniciales
INSERT INTO status (status_name) VALUES ('Pendiente'), ('Activo'), ('Inactivo');

CREATE TABLE attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_attribute INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    status INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_attribute) REFERENCES attributes(id) ON DELETE CASCADE,
    FOREIGN KEY (status) REFERENCES status(id)
);

CREATE TABLE attribute_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_attribute INT NOT NULL,
    value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_attribute) REFERENCES attributes(id) ON DELETE CASCADE
);
