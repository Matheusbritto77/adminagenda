#!/usr/bin/env sh
set -eu

# Ensure config cache is cleared so container environment variables take effect
if [ -f /var/www/html/artisan ]; then
    php /var/www/html/artisan config:clear || true
fi

# Detect DB configuration
DB_DRIVER="${DB_CONNECTION:-pgsql}"
DB_HOST_VAL="${DB_HOST:-}"

if [ -n "$DB_HOST_VAL" ]; then
    if [ -z "${DB_PORT:-}" ]; then
        if [ "$DB_DRIVER" = "pgsql" ]; then
            export DB_PORT="5432"
        else
            export DB_PORT="3306"
        fi
    fi

    echo "Waiting for database (${DB_DRIVER}) at ${DB_HOST_VAL}:${DB_PORT}..."
    until php -r '
$host = getenv("DB_HOST");
$driver = getenv("DB_CONNECTION") ?: "pgsql";
$port = (int) (getenv("DB_PORT") ?: ($driver === "pgsql" ? 5432 : 3306));
$user = getenv("DB_USERNAME") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_DATABASE") ?: "";

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

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_TIMEOUT => 5,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection waiting: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
'; do
        sleep 2
    done

    echo "Database connected successfully! Running migrations..."
    if [ -f /var/www/html/artisan ]; then
        php /var/www/html/artisan migrate --force || echo "Migration warning: check logs if migration failed."
    fi
fi

if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    cp /var/www/html/.env.example /var/www/html/.env
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

exec "$@"

