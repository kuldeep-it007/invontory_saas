# Inventory & Distribution Platform — one-shot setup for Windows (PowerShell).
#
# Installs backend + frontend dependencies, prepares environment files, and
# runs the initial database migration/seed. After it finishes, start the three
# dev processes with the commands printed at the end (see RUN.md).
#
# Usage (from the repo root):
#   ./setup.ps1
#
# Requires: PHP 8.5+, Composer, Node 18.18+/20+, npm, a running MySQL + Redis.

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

Write-Host "==> Setting up backend (Laravel)..." -ForegroundColor Cyan
Push-Location (Join-Path $root "backend")

composer install

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "    Created backend/.env from template." -ForegroundColor Green
}

php artisan key:generate
php artisan migrate --force
php artisan db:seed

Pop-Location

Write-Host "==> Setting up frontend (Next.js)..." -ForegroundColor Cyan
Push-Location (Join-Path $root "frontend")

npm install

if (-not (Test-Path ".env.local")) {
    Copy-Item ".env.example" ".env.local"
    Write-Host "    Created frontend/.env.local from template." -ForegroundColor Green
}

Pop-Location

Write-Host ""
Write-Host "Setup complete. Start the app with three terminals:" -ForegroundColor Green
Write-Host "  1) cd backend  ; php artisan serve"
Write-Host "  2) cd backend  ; php artisan queue:work"
Write-Host "  3) cd frontend ; npm run dev"
Write-Host ""
Write-Host "Then open http://localhost:3000  (see RUN.md for details)."
