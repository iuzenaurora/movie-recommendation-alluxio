param([string]$Model, [switch]$Rollback)
$ErrorActionPreference="Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)
$modelRoot = (Resolve-Path 'models').Path
$stamp = [guid]::NewGuid().ToString('N')
if ($Rollback) {
    if ($Model) { throw 'Use either -Model or -Rollback' }
    $source = Join-Path $modelRoot 'previous'
} else {
    if (-not $Model) { throw 'Provide -Model or -Rollback' }
    $source = (Resolve-Path -LiteralPath $Model).Path
    if (Test-Path 'models/previous') { throw 'Archive models/previous before another activation' }
}
if (-not $source.StartsWith($modelRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Model must be inside the project models directory' }
if (-not (Test-Path -LiteralPath (Join-Path $source 'manifest.json'))) { throw 'Missing model manifest' }
$stage = Join-Path $modelRoot "staging-$stamp"
New-Item -ItemType Directory -Path $stage | Out-Null
Get-ChildItem -LiteralPath $source -Force | Copy-Item -Destination $stage -Recurse -Force
$relative = "models/staging-$stamp"
& docker compose --profile jobs run --rm spark python3 scripts/validate_package.py --model $relative
if ($LASTEXITCODE -ne 0) { throw 'Invalid package; running application unchanged' }
& docker compose --profile app stop web api
if ($LASTEXITCODE -ne 0) { throw 'Cannot stop application' }
if (Test-Path 'models/current') {
    $destination = if ($Rollback) { Join-Path $modelRoot "failed-$stamp" } else { Join-Path $modelRoot 'previous' }
    Move-Item -LiteralPath (Join-Path $modelRoot 'current') -Destination $destination
}
Move-Item -LiteralPath $stage -Destination (Join-Path $modelRoot 'current')
& docker compose --profile app up -d --wait --wait-timeout 240 mongo api web
if ($LASTEXITCODE -ne 0) { throw 'Startup failed. Inspect logs; use -Rollback if previous exists.' }
Write-Host 'Application healthy. Previous artifacts retained.'
