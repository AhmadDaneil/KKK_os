param(
    [Parameter(Mandatory=$true)]
    [string]$ManifestPath,

    [Parameter(Mandatory=$true)]
    [string]$BatchFolder
)

$ErrorActionPreference = "Stop"

function Pass($msg) { Write-Host "[PASS] $msg" -ForegroundColor Green }
function Fail($msg) { Write-Host "[FAIL] $msg" -ForegroundColor Red; $script:failed = $true }
function Info($msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }

$failed = $false

if (!(Test-Path $ManifestPath)) {
    throw "Manifest not found: $ManifestPath"
}

if (!(Test-Path $BatchFolder)) {
    throw "Batch folder not found: $BatchFolder"
}

$manifest = Get-Content $ManifestPath -Raw | ConvertFrom-Json
$orderId = $manifest.order.order_id
$packageCount = [int]$manifest.order.package_count
$expectedQty = [string]$manifest.order.card_quantity

Info "Order: $orderId"
Info "Package count: $packageCount"
Info "Expected qtykad: $expectedQty"

$stage1 = Join-Path $BatchFolder "1 Waiting Customer"
if (Test-Path $stage1) {
    Pass "Stage folder exists: 1 Waiting Customer"
} else {
    Fail "Missing stage folder: 1 Waiting Customer"
}

$allFiles = Get-ChildItem -Path $BatchFolder -Recurse -File -ErrorAction SilentlyContinue

$jpgs = @($allFiles | Where-Object { $_.Extension -match '^\.(jpg|jpeg)$' })
$psds = @($allFiles | Where-Object { $_.Extension -ieq '.psd' })
$qrs  = @($allFiles | Where-Object {
    $_.FullName -match '[\\/]QR[\\/]' -and $_.Extension -match '^\.(png|jpg|jpeg)$'
})

if ($jpgs.Count -ge $packageCount) {
    Pass "JPEG output count is at least package count ($($jpgs.Count) >= $packageCount)"
} else {
    Fail "JPEG output count too low ($($jpgs.Count) < $packageCount)"
}

if ($psds.Count -ge $packageCount) {
    Pass "PSD output count is at least package count ($($psds.Count) >= $packageCount)"
} else {
    Fail "PSD output count too low ($($psds.Count) < $packageCount)"
}

if ($qrs.Count -ge $packageCount) {
    Pass "QR output count is at least package count ($($qrs.Count) >= $packageCount)"
} else {
    Fail "QR output count too low ($($qrs.Count) < $packageCount)"
}

$doublePcs = @($allFiles | Where-Object { $_.Name -match 'PCS\s+PCS' })
if ($doublePcs.Count -eq 0) {
    Pass "No 'PCS PCS' filename defect detected"
} else {
    Fail "'PCS PCS' defect detected in: $($doublePcs.FullName -join ', ')"
}

$sideNames = @($manifest.expected.package_sides)
foreach ($side in $sideNames) {
    $sideMatches = @($allFiles | Where-Object {
        $_.Name -match [regex]::Escape($side) -or
        $_.DirectoryName -match [regex]::Escape($side)
    })

    if ($sideMatches.Count -gt 0) {
        Pass "Found output evidence for side: $side"
    } else {
        Write-Host "[CHECK] No filename/folder contains side '$side'. This may be acceptable if current JSX naming omits side; visually verify outputs are independent." -ForegroundColor Yellow
    }
}

$logFiles = @($allFiles | Where-Object { $_.Extension -ieq '.log' -or $_.Name -match 'log' })
if ($logFiles.Count -gt 0) {
    Pass "Photoshop log file(s) detected: $($logFiles.Count)"
} else {
    Write-Host "[CHECK] No log file detected by filename. Verify JSX log output manually." -ForegroundColor Yellow
}

Write-Host ""
if ($failed) {
    Write-Host "RESULT: FAIL — investigate before approving Stage 9B." -ForegroundColor Red
    exit 1
}

Write-Host "RESULT: AUTOMATED FILE CHECKS PASS." -ForegroundColor Green
Write-Host "Manual visual checks are still required for text layers, QR placement, template correctness and no cross-side overwrite." -ForegroundColor Yellow
exit 0
