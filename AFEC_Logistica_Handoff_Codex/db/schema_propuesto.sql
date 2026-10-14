-- BORRADOR de modelo para revisión. NO aplicar directamente a producción.
-- MySQL 8 / MariaDB compatible en sus funciones comunes; confirmar versión.
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(120) NOT NULL UNIQUE,
 display_name VARCHAR(160) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE systems (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(255) NOT NULL UNIQUE,
 description TEXT NULL,
 archived_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE installations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 system_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(255) NOT NULL,
 body_name VARCHAR(255) NULL,
 location_label VARCHAR(255) NULL,
 tier VARCHAR(20) NULL,
 state VARCHAR(60) NOT NULL DEFAULT 'planned',
 sort_order INT NOT NULL DEFAULT 0,
 archived_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_inst_system FOREIGN KEY(system_id) REFERENCES systems(id),
 INDEX idx_inst_system (system_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE commodities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(120) NOT NULL UNIQUE,
 name_es VARCHAR(255) NOT NULL,
 name_en VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE requirements (
 installation_id BIGINT UNSIGNED NOT NULL,
 commodity_id BIGINT UNSIGNED NOT NULL,
 required_quantity BIGINT UNSIGNED NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (installation_id, commodity_id),
 FOREIGN KEY (installation_id) REFERENCES installations(id),
 FOREIGN KEY (commodity_id) REFERENCES commodities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 installation_id BIGINT UNSIGNED NOT NULL,
 commodity_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL,
 delta_quantity BIGINT NOT NULL,
 movement_type ENUM('delivery','reversal','adjustment','import') NOT NULL,
 reverses_movement_id BIGINT UNSIGNED NULL,
 idempotency_key VARCHAR(100) NOT NULL UNIQUE,
 note TEXT NULL,
 recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (installation_id,commodity_id) REFERENCES requirements(installation_id,commodity_id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (reverses_movement_id) REFERENCES movements(id),
 INDEX idx_mov_req_date (installation_id, commodity_id, recorded_at),
 INDEX idx_mov_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Pendiente: audit_log de cambio de requerimientos, autorización y migración.
