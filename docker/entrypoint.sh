#!/usr/bin/env sh
set -eu

# Ensure .env file exists
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure config cache is cleared so container environment variables take effect
if [ -f /var/www/html/artisan ]; then
    php /var/www/html/artisan config:clear || true
fi

# Run PHP script to resolve DB credentials and wait for connection
php -r '
$host = getenv("DB_HOST");
$driver = getenv("DB_CONNECTION");
$port = getenv("DB_PORT");
$user = getenv("DB_USERNAME");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_DATABASE");

if (file_exists("/var/www/html/.env")) {
    $lines = file("/var/www/html/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== "" && strpos($line, "#") !== 0 && strpos($line, "=") !== false) {
            list($k, $v) = explode("=", $line, 2);
            $k = trim($k);
            $v = trim($v, " \"\t\r\n");
            if ($k === "DB_HOST" && !$host) $host = $v;
            if ($k === "DB_CONNECTION" && !$driver) $driver = $v;
            if ($k === "DB_PORT" && !$port) $port = $v;
            if ($k === "DB_USERNAME" && !$user) $user = $v;
            if ($k === "DB_PASSWORD" && !$password) $password = $v;
            if ($k === "DB_DATABASE" && !$database) $database = $v;
        }
    }
}

$driver = $driver ?: "mysql";
$host = $host ?: "";
$port = (int) ($port ?: ($driver === "pgsql" ? 5432 : 3306));
$user = $user ?: "mysql";
$password = $password ?: "";
$database = $database ?: "";

if (empty($host)) {
    echo "No DB_HOST configured. Skipping database wait.\n";
    exit(0);
}

echo "Waiting for database ({$driver}) at {$host}:{$port}...\n";

if ($driver === "pgsql") {
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
} else {
    $dsn = "mysql:host={$host};port={$port};dbname={$database}";
}

$attempts = 0;
while ($attempts < 30) {
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "Database connected successfully!\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Database connection waiting: " . $e->getMessage() . PHP_EOL);
        sleep(2);
        $attempts++;
    }
}
exit(1);
'

if [ -f /var/www/html/artisan ]; then
    echo "Running database migrations..."
    php /var/www/html/artisan migrate --force || echo "Migration warning: check logs if migration failed."
fi

# Ensure storage directories exist with correct permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

if [ -f /var/www/html/artisan ] && [ ! -L /var/www/html/public/storage ] && [ ! -d /var/www/html/public/storage ]; then
    php /var/www/html/artisan storage:link --force >/dev/null 2>&1 || true
fi

# Start PHP-FPM in background
echo "Starting PHP-FPM in background..."
php-fpm -D

# If nginx is installed, start Nginx in foreground on port 80
if command -v nginx >/dev/null 2>&1; then
    echo "Starting Nginx web server on port 80..."
    exec nginx -g 'daemon off;'
fi

exec "$@"

