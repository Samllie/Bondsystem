#Requires -RunAsAdministrator
# Configures XAMPP Apache for HTTPS on sici-bonds.local and updates project .env.

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$vhostsPath = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
$sslConfPath = "C:\xampp\apache\conf\extra\httpd-ssl.conf"
$includeLine = 'Include "C:/projects/Bondsystem/xampp-vhost-ssl.conf"'

& (Join-Path $projectRoot "scripts\generate-local-ssl-cert.ps1")

$httpVhost = @'
<VirtualHost *:80>
    ServerName sici-bonds.local
    Redirect permanent / https://sici-bonds.local/
</VirtualHost>

'@

if (-not (Test-Path $vhostsPath)) {
    Write-Error "XAMPP vhosts file not found: $vhostsPath"
}

$vhostsContent = Get-Content -Path $vhostsPath -Raw

if ($vhostsContent -match "ServerName sici-bonds\.local") {
    $vhostsContent = [regex]::Replace(
        $vhostsContent,
        '(?s)<VirtualHost \*:80>\s*DocumentRoot "C:/projects/Bondsystem/public"[\s\S]*?</VirtualHost>\s*',
        $httpVhost
    )
    Set-Content -Path $vhostsPath -Value $vhostsContent.TrimEnd() + "`r`n" -NoNewline
    Write-Host "Updated HTTP vhost to redirect to HTTPS."
} else {
    Add-Content -Path $vhostsPath -Value "`r`n$httpVhost"
    Write-Host "Appended HTTP redirect vhost."
}

$sslContent = Get-Content -Path $sslConfPath -Raw
if ($sslContent -notmatch [regex]::Escape($includeLine)) {
    Add-Content -Path $sslConfPath -Value "`r`n$includeLine`r`n"
    Write-Host "Added SSL vhost include to httpd-ssl.conf."
} else {
    Write-Host "SSL vhost include already present."
}

$envPath = Join-Path $projectRoot ".env"
if (Test-Path $envPath) {
    $envContent = Get-Content -Path $envPath -Raw
    $envContent = $envContent -replace 'APP_URL=http://sici-bonds\.local', 'APP_URL=https://sici-bonds.local'

    if ($envContent -notmatch 'APP_FORCE_HTTPS=') {
        $envContent = $envContent -replace '(APP_URL=https://sici-bonds\.local)', "`$1`r`nAPP_FORCE_HTTPS=true"
    } else {
        $envContent = $envContent -replace 'APP_FORCE_HTTPS=.*', 'APP_FORCE_HTTPS=true'
    }

    if ($envContent -notmatch 'SESSION_SECURE_COOKIE=') {
        $envContent = $envContent -replace '(APP_FORCE_HTTPS=true)', "`$1`r`nSESSION_SECURE_COOKIE=true"
    } else {
        $envContent = $envContent -replace 'SESSION_SECURE_COOKIE=.*', 'SESSION_SECURE_COOKIE=true'
    }

    if ($envContent -notmatch 'VITE_DEV_SERVER_URL=') {
        $envContent = $envContent -replace '(SESSION_SECURE_COOKIE=true)', "`$1`r`nVITE_DEV_SERVER_URL=https://sici-bonds.local:5173"
    } else {
        $envContent = $envContent -replace 'VITE_DEV_SERVER_URL=.*', 'VITE_DEV_SERVER_URL=https://sici-bonds.local:5173'
    }

    Set-Content -Path $envPath -Value $envContent.TrimEnd() + "`r`n" -NoNewline
    Write-Host "Updated .env for HTTPS."
}

Write-Host ""
Write-Host "Restart Apache in XAMPP, then open https://sici-bonds.local"
Write-Host "Accept/trust the self-signed certificate if your browser warns you."
