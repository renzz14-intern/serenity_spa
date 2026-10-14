<?php
// Local database configuration. Never commit local_config.php.
if (!extension_loaded('mysqli')) {
    http_response_code(500);
    exit('The PHP MySQLi extension is required. Enable it in your PHP configuration.');
}

$localConfigPath = __DIR__ . '/local_config.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}
$dbConfig = $localConfig['database'] ?? [];
$host = $dbConfig['host'] ?? 'localhost';
$user = $dbConfig['username'] ?? 'root';
$password = $dbConfig['password'] ?? '';
$database = $dbConfig['database'] ?? 'users_db';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    error_log('Serenity Spa database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection failed. Check that MySQL is running and local_config.php is configured.');
}
$conn->set_charset('utf8mb4');
?>
