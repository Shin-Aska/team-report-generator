# Generates a Laravel APP_KEY and writes it into src/.env.
# Run this before building images — no PHP or Laravel needed on the host.

$envFile = Join-Path $PSScriptRoot "src\.env"

if (-not (Test-Path $envFile)) {
    Write-Error "src\.env not found. Copy src\.env.example to src\.env first."
    exit 1
}

$bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
$key = "base64:" + [Convert]::ToBase64String($bytes)

$content = Get-Content $envFile -Raw
if ($content -match '(?m)^APP_KEY=') {
    $content = $content -replace '(?m)^APP_KEY=.*', "APP_KEY=$key"
} else {
    $content += "`nAPP_KEY=$key"
}

Set-Content $envFile -Value $content -NoNewline
Write-Host "APP_KEY written to $envFile"
