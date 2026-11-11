# Inventory & Distribution Platform - automated Docker setup (Windows).
#
# Runs the COMPLETE stack with Docker only - no PHP, Node, MySQL, or Redis
# installed on the host. It:
#   1) verifies Docker is installed and running,
#   2) frees the ports the stack needs (stops any container holding them),
#   3) builds the API, worker, and frontend images,
#   4) starts everything (MySQL + Redis + API + worker + frontend),
#   5) waits for MySQL + the API to become healthy,
#   6) prints the URLs.
#
# Usage (from the repo root, in PowerShell):
#   ./docker-start.ps1
#   ./docker-start.ps1 -Fresh      # also wipe the database volume first
#
# Stop later with:  docker compose down   (add -v to also drop the DB volume)

[CmdletBinding()]
param(
    # Wipe the MySQL volume for a clean database before starting.
    [switch]$Fresh
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

# Ports the stack publishes on the host. If another container holds one, we
# stop that container so the stack can bind. Host apps on these ports must be
# freed manually.
$Ports = [ordered]@{
    "MySQL"    = 3306
    "Redis"    = 6379
    "API"      = 8000
    "Frontend" = 3000
}

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "    $msg" -ForegroundColor Yellow }

# 1) Docker present + running
Write-Step "Checking Docker..."
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is not installed or not on PATH. Install Docker Desktop: https://www.docker.com/products/docker-desktop/"
}
try {
    docker info *> $null
} catch {
    throw "Docker is installed but the engine is not running. Start Docker Desktop and re-run."
}
Write-Ok "Docker is running."

# 2) Free the required host ports
Write-Step "Freeing required host ports..."
foreach ($name in $Ports.Keys) {
    $port = $Ports[$name]
    $pattern = ":" + $port + "->"
    $holders = docker ps --format "{{.ID}} {{.Ports}}" 2>$null |
        Select-String -Pattern $pattern -SimpleMatch
    foreach ($line in $holders) {
        $id = ($line.ToString() -split "\s+")[0]
        $cname = docker inspect --format "{{.Name}}" $id 2>$null
        # Don't stop our own stack's containers; compose handles those.
        if ($cname -notlike "*invontory_saas*") {
            Write-Warn "Port $port ($name) is held by $cname - stopping it."
            docker stop $id *> $null
        }
    }
}
Write-Ok "Ports available."

# 3) Optional fresh database
if ($Fresh) {
    Write-Step "Removing existing stack + database volume (fresh start)..."
    docker compose down -v *> $null
    Write-Ok "Previous volumes removed."
}

# 4) Build images
Write-Step "Building images (first run can take several minutes)..."
docker compose build
Write-Ok "Images built."

# 5) Start the stack
Write-Step "Starting the stack..."
docker compose up -d
Write-Ok "Containers started."

# 6) Wait for MySQL to be healthy
Write-Step "Waiting for MySQL to be healthy..."
$deadline = (Get-Date).AddMinutes(3)
$health = ""
do {
    Start-Sleep -Seconds 3
    $health = docker inspect --format "{{.State.Health.Status}}" invontory_saas-mysql-1 2>$null
    if ($health -eq "healthy") { break }
} while ((Get-Date) -lt $deadline)

if ($health -ne "healthy") {
    Write-Warn "MySQL did not report healthy in time. Check: docker compose logs mysql"
} else {
    Write-Ok "MySQL is healthy."
}

# 7) Wait for the API to answer the health probe
Write-Step "Waiting for the API (migrations run on first boot)..."
$deadline = (Get-Date).AddMinutes(3)
$apiUp = $false
do {
    Start-Sleep -Seconds 4
    try {
        $res = Invoke-WebRequest -Uri "http://localhost:8000/api/health" -UseBasicParsing -TimeoutSec 4
        if ($res.StatusCode -eq 200) { $apiUp = $true; break }
    } catch {
        # not ready yet
    }
} while ((Get-Date) -lt $deadline)

if ($apiUp) { Write-Ok "API is responding." }
else { Write-Warn "API not responding yet. Watch logs: docker compose logs -f api" }

# 8) Summary
Write-Host ""
Write-Host "Stack is up." -ForegroundColor Green
Write-Host "  Frontend     http://localhost:3000"
Write-Host "  API health   http://localhost:8000/api/health"
Write-Host ""
Write-Host "Useful commands:"
Write-Host "  docker compose ps                 # service status"
Write-Host "  docker compose logs -f api        # tail the API"
Write-Host "  docker compose down               # stop the stack"
Write-Host "  docker compose down -v            # stop + wipe the database"
