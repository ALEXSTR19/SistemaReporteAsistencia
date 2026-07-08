USE asistencias_db;

-- NO MODIFICA la tabla attendancerecordinfo que llena SmartPSS Lite.
-- Crea tablas separadas para usuarios del sistema y correcciones/auditoría.

CREATE TABLE IF NOT EXISTS app_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario inicial: admin / admin123
INSERT INTO app_users (username, password_hash, full_name)
SELECT 'admin', '$2y$10$0fQAyK8U0AzCN/TB8ZiPyOyyIY4Y5gZISn7KWiRiw0f6Q85ZuALiS', 'Administrador'
WHERE NOT EXISTS (SELECT 1 FROM app_users WHERE username='admin');

CREATE TABLE IF NOT EXISTS app_attendance_overrides (
    record_hash CHAR(64) PRIMARY KEY,
    PersonName VARCHAR(36) NULL,
    PerSonCardNo VARCHAR(20) NULL,
    AttendanceDateTime BIGINT NULL,
    AttendanceState INT NULL,
    AttendanceMethod INT NULL,
    DeviceIPAddress VARCHAR(20) NULL,
    DeviceName VARCHAR(50) NULL,
    SnapshotsPath VARCHAR(200) NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    updated_by VARCHAR(50) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    record_hash CHAR(64) NOT NULL,
    action_type ENUM('EDIT','DELETE','RESTORE') NOT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    reason VARCHAR(255) NULL,
    username VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(record_hash),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
