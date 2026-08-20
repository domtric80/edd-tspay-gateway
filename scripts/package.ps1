param(
    [string]$Version = '0.1.1'
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PluginSource = Join-Path $ProjectRoot 'plugin\edd-tspay'
$Dist = Join-Path $ProjectRoot 'dist'
$Zip = Join-Path $Dist ("edd-tspay-$Version.zip")

New-Item -ItemType Directory -Force -Path $Dist | Out-Null
if (Test-Path -LiteralPath $Zip) {
    Remove-Item -LiteralPath $Zip -Force
}
& tar.exe -a -c -f $Zip -C (Split-Path -Parent $PluginSource) (Split-Path -Leaf $PluginSource)
if ($LASTEXITCODE -ne 0) {
    throw 'Creazione archivio plugin non riuscita.'
}

$Entries = & tar.exe -tf $Zip
if ($LASTEXITCODE -ne 0) {
    throw 'Impossibile verificare il contenuto dello ZIP.'
}
if ($Entries -match '\\') {
    throw 'Lo ZIP contiene separatori Windows non compatibili con WordPress/Linux.'
}
if ($Entries -notcontains 'edd-tspay/edd-tspay.php') {
    throw 'Lo ZIP non contiene il file principale nella posizione attesa.'
}

Write-Host "Creato: $Zip"
