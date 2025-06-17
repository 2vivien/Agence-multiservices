<?php

// tests/bootstrap.php

// Autoload Composer dependencies
// This assumes your 'vendor' directory is at the project root.
$autoloader = require __DIR__ . '/../vendor/autoload.php';

if (!$autoloader) {
    echo "Composer autoloader not found. Run 'composer install'.\n";
    exit(1);
}

// Load environment variables (e.g., from a .env.testing file) if your app uses them
// Example:
// if (class_exists(Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env.testing')) {
//     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
//     $dotenv->load();
// } elseif (class_exists(Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
//     // Fallback to .env if .env.testing doesn't exist, but configure for test environment
//     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
//     $dotenv->load();
//     // Ensure database connections, etc., are configured for a separate test database
//     // For example, by overriding specific $_ENV or getenv() values here.
// }


// Initialize application for integration tests if necessary
// This depends heavily on how your application is structured.
// If you have a central application factory or bootstrap script:
// require_once __DIR__ . '/../public/index.php'; // Or your app's entry point/bootstrap
// Or:
// $app = require __DIR__ . '/../app/bootstrap.php'; // Or however your app is created


// You might want to set a global constant to indicate tests are running
if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}

// Any other global setup for tests can go here
// e.g., setting default timezone, error reporting levels specific to tests

echo "PHPUnit Bootstrap Loaded.\n";
// Note: In a real setup, you might not echo from bootstrap unless debugging.

?>
