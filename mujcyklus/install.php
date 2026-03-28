<?php
require_once 'config.php';
$db = getDB();

$db->exec("
    CREATE TABLE IF NOT EXISTS mc_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100),
        age INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
    CREATE TABLE IF NOT EXISTS mc_user_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        settings JSON,
        day_data JSON,
        notifications JSON,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES mc_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$db->exec("
    CREATE TABLE IF NOT EXISTS mc_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45),
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "Tabulky vytvořeny úspěšně!";
