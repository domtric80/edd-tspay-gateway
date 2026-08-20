param(
    [switch]$Reset
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$EddSource = Join-Path (Split-Path -Parent $ProjectRoot) 'easy-digital-downloads.3.7.0.zip'
$EddTarget = Join-Path $ProjectRoot 'wordpress\wp-content\plugins\easy-digital-downloads'

Set-Location -LiteralPath $ProjectRoot

if ($Reset) {
    $resolvedProject = (Resolve-Path -LiteralPath $ProjectRoot).Path
    if ($resolvedProject -ne 'D:\Docker\WordPress\edd-tspay-gateway') {
        throw "Percorso progetto inatteso: $resolvedProject"
    }
    docker compose down --volumes --remove-orphans
}

if (-not (Test-Path -LiteralPath (Join-Path $EddTarget 'easy-digital-downloads.php'))) {
    if (-not (Test-Path -LiteralPath $EddSource)) {
        throw "Archivio EDD non trovato: $EddSource"
    }
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $EddTarget) | Out-Null
    Expand-Archive -LiteralPath $EddSource -DestinationPath (Split-Path -Parent $EddTarget) -Force
}

docker compose up -d db tspay-mock wordpress

$deadline = (Get-Date).AddMinutes(4)
do {
    Start-Sleep -Seconds 3
    try {
        $response = Invoke-WebRequest -Uri 'http://localhost:8080/wp-login.php' -UseBasicParsing -TimeoutSec 5
        $ready = $response.StatusCode -eq 200
    } catch {
        $ready = $false
    }
} until ($ready -or (Get-Date) -gt $deadline)

if (-not $ready) {
    docker compose logs --tail=100 wordpress db
    throw 'WordPress non è diventato disponibile entro il timeout.'
}

$savedErrorActionPreference = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
$null = docker compose run --rm wpcli wp core is-installed 2>&1
$isInstalledExitCode = $LASTEXITCODE
$ErrorActionPreference = $savedErrorActionPreference
if ($isInstalledExitCode -ne 0) {
    docker compose run --rm wpcli wp core install --url=http://localhost:8080 --title='EDD TS Pay Lab' --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
	if ($LASTEXITCODE -ne 0) { throw 'Installazione WordPress non riuscita.' }
}

docker compose run --rm wpcli wp plugin activate easy-digital-downloads edd-tspay
if ($LASTEXITCODE -ne 0) { throw 'Attivazione plugin non riuscita.' }
docker compose run --rm wpcli wp eval-file /workspace/scripts/configure-wordpress.php
if ($LASTEXITCODE -ne 0) { throw 'Configurazione WordPress non riuscita.' }

Write-Host ''
Write-Host 'Ambiente pronto:'
Write-Host '  Sito:  http://localhost:8080'
Write-Host '  Admin: http://localhost:8080/wp-admin/  (admin / admin)'
Write-Host '  Mock:  http://localhost:8090'
