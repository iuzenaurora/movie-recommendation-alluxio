param([string]$Data = 'data/processed/ml32m-full-v1')
$ErrorActionPreference = 'Stop'
$manifest = Get-Content (Join-Path $Data 'manifest.json') -Raw | ConvertFrom-Json
if ($manifest.datasetVersion -ne 'ml-5b41ebbd9d02' -or $manifest.rawRows -ne 32000204 -or $manifest.splitCounts.train -ne 25520897) {
    throw 'Not the agreed full MovieLens32M dataset.'
}
$properties = @($manifest.fileChecksums.PSObject.Properties)
if ($properties.Count -eq 0) { throw 'No file checksums in manifest.' }
foreach ($p in $properties) {
    $path = Join-Path $Data $p.Name
    if (-not (Test-Path $path -PathType Leaf)) { throw "Missing $path" }
    $actual = (Get-FileHash $path -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne ([string]$p.Value).ToLowerInvariant()) { throw "Checksum differs: $path" }
}
$actualFiles = @(Get-ChildItem $Data -Recurse -File -Filter '*.parquet')
if ($actualFiles.Count -ne $properties.Count) { throw 'Unexpected or missing Parquet files.' }
Write-Host "Verified $($properties.Count) Parquet files; dataset $($manifest.datasetVersion)."
