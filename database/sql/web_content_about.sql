USE rossi_equipamientos;

CREATE TABLE web_content_about (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    video_giro VARCHAR(255),
    video_showroom VARCHAR(255),
    CONSTRAINT fk_user_id_about FOREIGN KEY (id_user) REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE gallery_web_content_about (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_web_content_about BIGINT UNSIGNED NOT NULL,
    file VARCHAR(255),
    CONSTRAINT fk_web_content_about FOREIGN KEY (id_web_content_about) REFERENCES web_content_about(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE gallery_web_content_about
ADD COLUMN thumbnail_file VARCHAR(255) AFTER file;