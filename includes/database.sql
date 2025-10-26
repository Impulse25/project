-- database.sql - Создание базы данных и таблиц

CREATE DATABASE IF NOT EXISTS svgtk_requests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE svgtk_requests;

-- Таблица пользователей
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('teacher', 'director', 'technician', 'admin') NOT NULL,
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица кабинетов
CREATE TABLE cabinets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cabinet_number VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица заявок
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_type ENUM('repair', 'software', '1c_database') NOT NULL,
    created_by INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    cabinet VARCHAR(50) NOT NULL,
    
    -- Для ремонта
    equipment_type VARCHAR(100),
    inventory_number VARCHAR(50),
    description TEXT,
    
    -- Для установки ПО
    computer_inventory VARCHAR(50),
    software_list TEXT,
    justification TEXT,
    
    -- Для 1С
    group_number VARCHAR(50),
    database_purpose TEXT,
    students_list TEXT,
    
    status ENUM('pending', 'approved', 'rejected', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    approved_by INT,
    approved_at DATETIME,
    rejection_reason TEXT,
    
    assigned_to INT,
    started_at DATETIME,
    completed_at DATETIME,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица комментариев
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка тестовых пользователей (пароль: 123456)
INSERT INTO users (username, password, full_name, role, position) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор', 'admin', 'Системный администратор'),
('director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Темирбулатова А.А.', 'director', 'Директор СВГТК'),
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Иванов Иван Иванович', 'teacher', 'Преподаватель информатики'),
('teacher2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Петрова Мария Сергеевна', 'teacher', 'Преподаватель математики'),
('tech1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Сидоров Петр Васильевич', 'technician', 'Системный техник'),
('tech2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Козлов Алексей Николаевич', 'technician', 'Системный техник');

-- Вставка начальных кабинетов
INSERT INTO cabinets (cabinet_number, description) VALUES
('101', 'Компьютерный класс'),
('102', 'Компьютерный класс'),
('103', 'Компьютерный класс'),
('104', 'Учебный кабинет'),
('105', 'Учебный кабинет'),
('201', 'Лаборатория программирования'),
('202', 'Компьютерный класс'),
('203', 'Учебный кабинет'),
('204', 'Учебный кабинет'),
('205', 'Учебный кабинет'),
('301', 'Компьютерный класс'),
('302', 'Учебный кабинет'),
('303', 'Учебный кабинет'),
('304', 'Учебный кабинет'),
('305', 'Учебный кабинет'),
('401', 'Компьютерный класс'),
('402', 'Учебный кабинет'),
('403', 'Учебный кабинет'),
('404', 'Учебный кабинет'),
('405', 'Учебный кабинет'),
('Актовый зал', 'Актовый зал колледжа'),
('Библиотека', 'Библиотека колледжа'),
('Лаборатория', 'Компьютерная лаборатория');