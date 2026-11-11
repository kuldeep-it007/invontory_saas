#!/usr/bin/env bash
# Inventory & Distribution Platform — one-shot setup for macOS / Linux (bash).
#
# Installs backend + frontend dependencies, prepares environment files, and
# runs the initial database migration/seed. After it finishes, start the three
# dev processes with the commands printed at the end (see RUN.md).
#
# Usage (from the repo root):
#   chmod +x setup.sh && ./setup.sh
#
# Requires: PHP 8.5+, Composer, Node 18.18+/20+, npm, a running MySQL + Redis.

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "==> Setting up backend (Laravel)..."
cd "$ROOT/backend"

composer install

if [ ! -f .env ]; then
  cp .env.example .env
  echo "    Created backend/.env from template."
fi

php artisan key:generate
php artisan migrate --force
php artisan db:seed

echo "==> Setting up frontend (Next.js)..."
cd "$ROOT/frontend"

npm install

if [ ! -f .env.local ]; then
  cp .env.example .env.local
  echo "    Created frontend/.env.local from template."
fi

cat <<'EOF'

Setup complete. Start the app with three terminals:
  1) cd backend  && php artisan serve
  2) cd backend  && php artisan queue:work
  3) cd frontend && npm run dev

Then open http://localhost:3000  (see RUN.md for details).
EOF
