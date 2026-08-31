#!/usr/bin/env sh
set -eu

# Ensure .env file exists
if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Load non-empty .env variables into shell environment so .env takes precedence over container defaults
if [ -f /var/www/html/.env ]; then
    eval $(php -r '
    $lines = file("/var/www/html/.env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== "" && strpos($line, "#") !== 0 && strpos($line, "=") !== false) {
            list($k, $v) = explode("=", $line, 2);
            $k = trim($k);
            $v = trim($v, " \"\t\r\n");
            if (preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/", $k) && $v !== "") {
                echo "export " . $k . "=" . escapeshellarg($v) . "\n";
            }
        }
    }
    ')
fi

# Ensure APP_KEY is set or generate one if missing
if [ -f /var/www/html/artisan ]; then
    APP_KEY_VAL="${APP_KEY:-}"
    if [ -z "$APP_KEY_VAL" ]; then
        echo "No APP_KEY specified. Generating application key..."
        php /var/www/html/artisan key:generate --force || true
        if [ -f /var/www/html/.env ]; then
            NEW_KEY=$(grep "^APP_KEY=" /var/www/html/.env 2>/dev/null | cut -d'=' -f2-)
            if [ -n "$NEW_KEY" ]; then
                export APP_KEY="$NEW_KEY"
            fi
        fi
    fi
fi

# Ensure config cache is cleared so updated environment variables take effect
if [ -f /var/www/html/artisan ]; then
    php /var/www/html/artisan config:clear || true
fi

# Run PHP script to resolve DB credentials and wait for connection
php -r '
$driver   = getenv("DB_CONNECTION") ?: "mysql";
$host     = getenv("DB_HOST") ?: "";
$port     = (int) (getenv("DB_PORT") ?: ($driver === "pgsql" ? 5432 : 3306));
$user     = getenv("DB_USERNAME") ?: "mysql";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_DATABASE") ?: "";

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

