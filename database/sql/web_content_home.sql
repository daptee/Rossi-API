USE rossi_equipamientos;

CREATE TABLE web_content_home (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    CONSTRAINT fk_user_id_home FOREIGN KEY (id_user) REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE web_content_home_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_web_content_home BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('image', 'video') NOT NULL,
    path VARCHAR(255) NOT NULL,
    CONSTRAINT fk_web_content_home FOREIGN KEY (id_web_content_home) REFERENCES web_content_home(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE web_content_home_files
ADD COLUMN thumbnail_path VARCHAR(255) AFTER path;