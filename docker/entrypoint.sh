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
    $env = file_get_contents("/var/www/html/.env");
    if (!$host && preg_match("/^DB_HOST=(.*)$/m", $env, $m)) $host = trim($m[1], "\"' \r\n");
    if (!$driver && preg_match("/^DB_CONNECTION=(.*)$/m", $env, $m)) $driver = trim($m[1], "\"' \r\n");
    if (!$port && preg_match("/^DB_PORT=(.*)$/m", $env, $m)) $port = trim($m[1], "\"' \r\n");
    if (!$user && preg_match("/^DB_USERNAME=(.*)$/m", $env, $m)) $user = trim($m[1], "\"' \r\n");
    if (!$password && preg_match("/^DB_PASSWORD=(.*)$/m", $env, $m)) $password = trim($m[1], "\"' \r\n");
    if (!$database && preg_match("/^DB_DATABASE=(.*)$/m", $env, $m)) $database = trim($m[1], "\"' \r\n");
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

switch ($driver) {
    case "pgsql":
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        break;
    case "mysql":
    case "mariadb":
        $dsn = "mysql:host={$host};port={$port};dbname={$database}";
        break;
    default:
        $dsn = "{$driver}:host={$host};port={$port};dbname={$database}";
        break;
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

