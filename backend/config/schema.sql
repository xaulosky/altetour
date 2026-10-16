CREATE TABLE IF NOT EXISTS admins (
    email VARCHAR(190) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS programs (
    slug VARCHAR(100) PRIMARY KEY,
    data JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS leads (
    id CHAR(32) PRIMARY KEY,
    reference VARCHAR(20) NOT NULL UNIQUE,
    idempotency_key VARCHAR(80) NOT NULL UNIQUE,
    fingerprint CHAR(64) NOT NULL,
    data JSON NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'nuevo',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX leads_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
