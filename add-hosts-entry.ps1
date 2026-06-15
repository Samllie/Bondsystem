#Requires -RunAsAdministrator
# Adds sici-bonds.local to the Windows hosts file (required for https://sici-bonds.local)

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entry = "127.0.0.1 sici-bonds.local"

$content = Get-Content -Path $hostsPath -Raw

if ($content -match "sici-bonds\.local") {
    Write-Host "Entry already exists in hosts file."
} else {
    Add-Content -Path $hostsPath -Value $entry
    Write-Host "Added: $entry"
}

ipconfig /flushdns | Out-Null
Write-Host "DNS cache flushed. Run scripts/setup-xampp-https.ps1 as Administrator, then open https://sici-bonds.local"
