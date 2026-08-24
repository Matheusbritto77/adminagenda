#!/usr/bin/env sh
set -eu

if [ -n "${DB_HOST:-}" ]; then
    if [ "${DB_CONNECTION:-mysql}" = "pgsql" ]; then
        db_port="${DB_PORT:-5432}"
    else
        db_port="${DB_PORT:-3306}"
    fi

    echo "Waiting for database at ${DB_HOST}:${db_port}..."
    until php -r '
$host = getenv("DB_HOST");
$driver = getenv("DB_CONNECTION") ?: "mysql";
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
