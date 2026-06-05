#!/bin/sh
set -e

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Ready. Run the import with:"
echo "  docker compose exec app php bin/console app:import-products-from-file"

exec "$@"
