# Generates a self-signed TLS certificate for local development (sici-bonds.local).
# Requires XAMPP OpenSSL (C:\xampp\apache\bin\openssl.exe).

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$certDir = Join-Path $projectRoot "storage\certs\local"
$openssl = "C:\xampp\apache\bin\openssl.exe"
$opensslConfig = Join-Path $certDir "openssl-san.cnf"

if (-not (Test-Path $openssl)) {
    Write-Error "OpenSSL not found at $openssl. Install XAMPP or set OPENSSL path."
}

New-Item -ItemType Directory -Force -Path $certDir | Out-Null

$keyPath = Join-Path $certDir "server.key"
$crtPath = Join-Path $certDir "server.crt"

if ((Test-Path $keyPath) -and (Test-Path $crtPath)) {
    Write-Host "Certificate already exists:"
    Write-Host "  $crtPath"
    Write-Host "  $keyPath"
    Write-Host "Delete them first if you need a new certificate."
    exit 0
}

Write-Host "Generating self-signed certificate for sici-bonds.local..."

& $openssl req -x509 -nodes -days 825 -newkey rsa:2048 `
    -keyout $keyPath `
    -out $crtPath `
    -config $opensslConfig `
    -extensions v3_req

Write-Host "Created:"
Write-Host "  $crtPath"
Write-Host "  $keyPath"
Write-Host ""
Write-Host "Trust this certificate in your browser/OS for a warning-free experience."
Write-Host "Windows: double-click server.crt > Install Certificate > Local Machine > Trusted Root."
