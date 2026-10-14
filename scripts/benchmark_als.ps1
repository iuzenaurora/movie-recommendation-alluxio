param(
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]*$')][string]$Dataset = 'ml32m-full-v1',
    [ValidateRange(1,100)][int]$Repeats = 5,
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]*$')][string]$RunName = ('ml32m-als-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$manifest = "data/processed/$Dataset/manifest.json"
if (-not (Test-Path $manifest)) { throw "Missing $manifest" }
$resultDir = "experiments/als-benchmark/$RunName"
if (Test-Path $resultDir) { throw 'Experiment directory already exists; choose a new RunName.' }
New-Item -ItemType Directory -Path $resultDir | Out-Null
$datasetPath = "/datasets/$Dataset"
$trainPath = "$datasetPath/split/split=train"

function Invoke-Dc([string[]]$DockerArgs) {
    # Java CLI can print normal diagnostics to stderr. Inspect exit status explicitly.
    $ErrorActionPreference = 'Continue'
    $lines = @(& docker compose @DockerArgs 2>&1)
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw ("Docker command failed ($code): " + ($lines -join "`n")) }
    return ($lines | ForEach-Object { "$_" })
}

function Save-State([string]$Prefix) {
    Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','du','-s',$trainPath) |
        Out-File "$resultDir/$Prefix-cache.txt" -Encoding utf8
    Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fsadmin','report','metrics') |
        Out-File "$resultDir/$Prefix-metrics.txt" -Encoding utf8
}

function Assert-Cache([bool]$Warm) {
    $lines = @(Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','du','-s',$trainPath))
    $rows = @($lines | Where-Object { $_ -match '^\s*\d+\s+\d+\s+\(' })
    if ($rows.Count -ne 1) { throw ('Cannot parse du output: ' + ($lines -join "`n")) }
    if ($rows[0] -notmatch '^\s*(\d+)\s+(\d+)\s+\(') { throw 'Cannot parse cache bytes.' }
    $size = [long]$Matches[1]; $cached = [long]$Matches[2]
    if ($size -le 0) { throw 'Train directory is empty.' }
    if ($Warm -and $cached -ne $size) { throw "H2 requires all train bytes cached: $cached / $size" }
    if ((-not $Warm) -and $cached -ne 0) { throw "H1 requires zero cached train bytes: $cached / $size" }
    Write-Host "Train cache: $cached / $size bytes"
}

function Run-Trial([string]$Label, [int]$Repeat) {
    $data = if ($Label -eq 'H0') { "hdfs://namenode:9000$datasetPath" } else { "alluxio://alluxio-master:19998$datasetPath" }
    Save-State "$Label-$Repeat-before"
    Write-Host "Starting $Label repeat $Repeat / $Repeats"
    $argsList = @('--profile','jobs','run','--rm','spark','bash','scripts/spark.sh','src/benchmark_als.py',
        '--data',$data,'--manifest',$manifest,'--label',$Label,'--repeat',"$Repeat",'--results',$resultDir,
        '--rank','20','--reg','0.1','--max-iter','10','--num-blocks','16','--seed','42')
    $ErrorActionPreference = 'Continue'
    & docker compose @argsList 2>&1 | Tee-Object -FilePath "$resultDir/$Label-$Repeat-spark.log"
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw "ALS trial $Label-$Repeat failed ($code). See its spark.log; stop this experiment." }
    Save-State "$Label-$Repeat-after"
}

Invoke-Dc -DockerArgs @('--profile','app','stop') | Out-Host
Invoke-Dc -DockerArgs @('exec','namenode','hdfs','dfs','-ls',$trainPath) | Out-Host
Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fsadmin','report','capacity') |
    Out-File "$resultDir/capacity-before.txt" -Encoding utf8
Invoke-Dc -DockerArgs @('config') | Out-File "$resultDir/compose-effective.txt" -Encoding utf8
Invoke-Dc -DockerArgs @('version') | Out-File "$resultDir/compose-version.txt" -Encoding utf8
$settings = [ordered]@{dataset=$Dataset; repeats=$Repeats; runName=$RunName; rank=20; regParam=0.1;
    maxIter=10; numUserBlocks=16; numItemBlocks=16; seed=42;
    input=$trainPath; sparkCache='MEMORY_AND_DISK'; osCache='uncontrolled';
    note='Benchmark read+fixed ALS only; no validation, test, model export or web.'}
$settings | ConvertTo-Json | Out-File "$resultDir/settings.json" -Encoding utf8
Copy-Item $manifest "$resultDir/dataset-manifest.json"
for ($r = 1; $r -le $Repeats; $r++) {
    $blocks = if ($r % 2 -eq 1) { @('direct','alluxio') } else { @('alluxio','direct') }
    foreach ($block in $blocks) {
        if ($block -eq 'direct') { Run-Trial 'H0' $r }
        else {
            # Clear the whole dataset, avoiding old ratings occupying train cache capacity.
            Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','free','-f',$datasetPath) | Out-Host
            Assert-Cache $false
            Run-Trial 'H1' $r
            # Synchronous load: the project does not deploy Alluxio job services.
            Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','load',$trainPath) | Out-Host
            Assert-Cache $true
            Run-Trial 'H2' $r
        }
    }
}
Invoke-Dc -DockerArgs @('--profile','jobs','run','--rm','spark','python3','scripts/summarize_als.py','--results',$resultDir) | Out-Host
Write-Host "Completed. Results: $resultDir/summary-als.json"
