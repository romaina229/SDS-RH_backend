#!/usr/bin/env bash
set -euo pipefail

echo "==> Nettoyage des caches de configuration…"
php artisan config:clear
php artisan route:clear
php artisan view:clear

if [ -z "${APP_KEY:-}" ]; then
    echo "==> Aucune APP_KEY détectée, génération automatique…"
    php artisan key:generate --force
fi

php artisan storage:link || true

echo "==> Exécution des migrations (forcé temporairement)…"
php artisan migrate --force

echo "==> Exécution des seeders (forcé temporairement)…"
php artisan db:seed --force

echo "==> Mise en cache de la configuration pour la production…"
php artisan config:cache
php artisan route:cache

PORT="${PORT:-8000}"
echo "==> Démarrage du serveur sur le port ${PORT}…"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"