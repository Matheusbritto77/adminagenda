#!/usr/bin/env sh
set -eu

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until php -r '
$host = getenv("DB_HOST");
$port = (int) (getenv("DB_PORT") ?: 3306);
$user = getenv("DB_USERNAME") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_DATABASE") ?: "";
$dsn = "mysql:host={$host};port={$port};dbname={$database}";
try {
    new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 5]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
'; do
        sleep 2
    done
fi

if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

exec "$@"
