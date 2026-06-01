# Run this script as Administrator (right-click PowerShell → Run as administrator)
# Adds bondsystem.test to your hosts file (only needed for Option B in xampp-vhost.conf)

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entry = "127.0.0.1 bondsystem.test"

if (Select-String -Path $hostsPath -Pattern "bondsystem\.test" -Quiet) {
    Write-Host "Entry already exists in hosts file."
} else {
    Add-Content -Path $hostsPath -Value $entry
    Write-Host "Added: $entry"
}
