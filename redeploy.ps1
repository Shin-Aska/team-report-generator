# Deploys (first time) or redeploys the stack with the latest code and src/.env.
# Rebuilds app/web images, recreates containers, generates APP_KEY if empty,
# clears stale Laravel caches, runs migrations, restarts web last (nginx IP).
#
# Usage:
#   .\redeploy.ps1            # build + recreate + migrate
#   .\redeploy.ps1 -Seed      # also seed demo data (needed on first deploy)
#   .\redeploy.ps1 -SkipBuild # skip image rebuild (env/config-only change)

param(
    [switch]$Seed,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$Compose = if (Get-Command podman -ErrorAction SilentlyContinue) { 'podman' } elseif (Get-Command docker -ErrorAction SilentlyContinue) { 'docker' } else { Write-Error 'podman or docker is required.'; exit 1 }
Write-Host "Using: $Compose compose"

if (-not (Test-Path (Join-Path $PSScriptRoot 'src\.env'))) {
    Write-Error 'src\.env not found. Copy src\.env.example to src\.env first.'
    exit 1
}

$envContent = Get-Content (Join-Path $PSScriptRoot 'src\.env') -Raw
if ($envContent -match '(?m)^APP_KEY=\s*$') {
    Write-Host "`n==> APP_KEY is empty - generating one..."
    $bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
    $key = 'base64:' + [Convert]::ToBase64String($bytes)
    $envContent = $envContent -replace '(?m)^APP_KEY=\s*$', "APP_KEY=$key"
    Set-Content (Join-Path $PSScriptRoot 'src\.env') -Value $envContent -NoNewline
}

if (-not $SkipBuild) {
    Write-Host "`n==> Building images (app, web)..."
    & $Compose compose build app web
    if ($LASTEXITCODE -ne 0) { Write-Error "Build failed."; exit 1 }
}

Write-Host "`n==> Clearing stale Laravel caches (host-side, bind-mounted)..."
Get-ChildItem (Join-Path $PSScriptRoot 'src\bootstrap\cache\*.php') -ErrorAction SilentlyContinue | Remove-Item -Force

Write-Host "`n==> Recreating containers with current .env..."
& $Compose compose up -d --force-recreate
if ($LASTEXITCODE -ne 0) { Write-Error "compose up failed."; exit 1 }

Write-Host "`n==> Discovering packages and caching config..."
& $Compose compose exec -T app sh -c "php artisan package:discover --ansi >/dev/null 2>&1; php artisan config:clear && php artisan config:cache" | Out-Null

Write-Host "`n==> Running migrations..."
& $Compose compose exec -T app php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Write-Warning "migrate reported an error - review output above." }

if ($Seed) {
    Write-Host "`n==> Seeding demo data..."
    & $Compose compose exec -T app php artisan db:seed --force
}

Write-Host "`n==> Restarting web last (nginx re-resolves app IP)..."
& $Compose compose restart web | Out-Null
Start-Sleep 3

Write-Host "`n==> Status:"
& $Compose compose ps
Write-Host "`nDone. App: http://localhost:8080"
