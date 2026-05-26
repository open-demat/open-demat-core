#!/usr/bin/env bash
set -euo pipefail

echo "📥 Pulling latest changes..."
git pull --ff-only

echo "🔧 Building composer.json..."
bash bin/composer-build

echo "📦 Running composer update..."
composer update "$@"

echo "✅ Done."