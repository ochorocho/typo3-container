<?php

// Database connection details
$host = getenv('TYPO3_DB_HOST');
$dbname = getenv('TYPO3_DB_DBNAME');
$user = getenv('TYPO3_DB_USERNAME');
$password = getenv('TYPO3_DB_PASSWORD');
$port = getenv('TYPO3_DB_PORT');

// MariaDB/MySQL
$availableDriver = [
    'pdo_mysql' => '',
    'pdo_pgsql' => '',
    // Currently not supported
    // 'mysqli' => '',
    // 'pdo_sqlite' => '',
];

$driver = getenv('TYPO3_DB_DRIVER');

try {
    // Connect to DB
    if($driver === 'pdo_mysql') {
        $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $dbname .";user=" . $user . ";password=" . $password;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        // Create table
        $pdo->query("CREATE TABLE IF NOT EXISTS MyGuests (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            firstname VARCHAR(30) NOT NULL,
            lastname VARCHAR(30) NOT NULL,
            email VARCHAR(50),
            reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

    } elseif ($driver === 'pdo_pgsql') {
        $dsn = "pgsql:host=db;port=5432;dbname=db;user=db;password=db";
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create table
        $pdo->query('CREATE TABLE IF NOT EXISTS MyGuests (
            id SERIAL PRIMARY KEY,
            firstname VARCHAR(255) NOT NULL,
            lastname VARCHAR(255) NOT NULL,
            email VARCHAR(50),
            reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );');

    } else {
        http_response_code(500);
        echo "‼️ Driver not supported";
        return;
    }
} catch (\Error $e) {
    http_response_code(500);

    echo "‼️ Error: " . $e->getMessage();
} catch (\Throwable $e) {
    http_response_code(500);

    echo "‼️ Throwable: " . $e->getMessage();
}

// Insert record
$sql = "INSERT INTO MyGuests (firstname, lastname, email) VALUES ('John', 'Doe', 'john@example.com')";
$pdo->query($sql);

// Select all records in table
$result = $pdo->query("SELECT id, firstname, lastname FROM MyGuests");

echo json_encode($result->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR);
