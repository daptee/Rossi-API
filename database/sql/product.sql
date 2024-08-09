USE rossi_equipamientos;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    status INT NOT NULL,
    main_img VARCHAR(255),
    main_video VARCHAR(255),
    file_data_sheet VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de estado de los productos
CREATE TABLE product_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL
);

-- Insertar estados iniciales
INSERT INTO product_status (status_name) VALUES ('Pendiente'), ('Activo'), ('Inactivo');

-- Tabla de galería de productos
CREATE TABLE product_galleries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT,
    file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabla de categorías de productos
CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT,
    id_categorie INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (id_categorie) REFERENCES products_categories(id) ON DELETE CASCADE
);

-- Tabla de materiales de productos
CREATE TABLE product_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT,
    id_material INT,
    value VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (id_material) REFERENCES materials(id) ON DELETE CASCADE
);

-- Tabla de atributos de productos
CREATE TABLE product_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT,
    id_attribute INT,
    value VARCHAR(255),
    img VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (id_attribute) REFERENCES attributes(id) ON DELETE CASCADE
);
