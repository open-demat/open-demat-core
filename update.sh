#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(pwd -P)"

# Récupère APP_ENV depuis .env.local, puis .env, sinon preprod
get_env_value() {
  local key="$1"
  local file="$2"

  if [[ -f "$file" ]]; then
    grep -E "^${key}=" "$file" \
      | tail -n1 \
      | cut -d '=' -f2- \
      | sed -e 's/^["'\'']//;s/["'\'']$//'
  fi
}

APP_ENV="${APP_ENV:-}"

if [[ -z "$APP_ENV" ]]; then
  APP_ENV="$(get_env_value APP_ENV "$APP_DIR/.env.local" || true)"
fi

if [[ -z "$APP_ENV" ]]; then
  APP_ENV="$(get_env_value APP_ENV "$APP_DIR/.env" || true)"
fi

APP_ENV="${APP_ENV:-preprod}"

echo "📁 Application directory: $APP_DIR"
echo "🌍 Symfony environment: $APP_ENV"

echo "📥 Pulling latest changes..."
git pull --ff-only

echo "🔧 Building composer.json..."
bash bin/composer-build

echo "📦 Running composer update..."
composer update "$@"

echo "🗑️  Clearing Symfony cache..."
sudo -n -u www-data /usr/bin/php "$APP_DIR/bin/console" cache:clear --env="$APP_ENV"

echo "✅ Done."
