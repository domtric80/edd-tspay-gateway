$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PluginSource = Join-Path $ProjectRoot 'plugin\edd-tspay'
$Dist = Join-Path $ProjectRoot 'dist'
$Zip = Join-Path $Dist 'edd-tspay-0.1.0.zip'

New-Item -ItemType Directory -Force -Path $Dist | Out-Null
if (Test-Path -LiteralPath $Zip) {
    Remove-Item -LiteralPath $Zip -Force
}
Compress-Archive -LiteralPath $PluginSource -DestinationPath $Zip -CompressionLevel Optimal
Write-Host "Creato: $Zip"

