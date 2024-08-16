USE rossi_equipamientos;

CREATE TABLE web_content_home (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    id_user BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    CONSTRAINT fk_user_id FOREIGN KEY (id_user) REFERENCES users(id)
);