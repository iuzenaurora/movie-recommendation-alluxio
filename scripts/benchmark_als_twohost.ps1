param(
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]*$')][string]$Dataset = 'ml32m-full-v1',
    [ValidateRange(1,100)][int]$Repeats = 5,
    [ValidatePattern('^[A-Za-z0-9][A-Za-z0-9_-]*$')][string]$RunName = ('ml32m-lan-als-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$manifest = "data/processed/$Dataset/manifest.json"
if (-not (Test-Path $manifest)) { throw "Missing $manifest" }
$envFile = 'twohost/.env.compute'
if (-not (Test-Path $envFile)) { throw 'Copy/edit twohost/.env.compute first.' }
$composePrefix = @('--env-file',$envFile,'-f','twohost/compose.compute.yaml')
$storageLine = @(Get-Content $envFile | Where-Object { $_ -match '^STORAGE_IP\s*=' })
if ($storageLine.Count -ne 1) { throw 'Need exactly one STORAGE_IP in .env.compute.' }
$storageAddress = ($storageLine[0] -split '=',2)[1].Trim()
$parsedAddress = $null
if (-not [System.Net.IPAddress]::TryParse($storageAddress,[ref]$parsedAddress) -or
    $parsedAddress.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork -or
    [System.Net.IPAddress]::IsLoopback($parsedAddress)) { throw 'STORAGE_IP must be the IPv4 LAN address of machine A.' }
if (-not (Test-Path 'src/benchmark_als.py') -or -not (Test-Path 'scripts/summarize_als.py')) { throw 'Missing benchmark source/scripts.' }
$resultDir = "experiments/als-benchmark/$RunName"
if (Test-Path $resultDir) { throw 'Experiment directory already exists; choose a new RunName.' }
New-Item -ItemType Directory -Path $resultDir | Out-Null
$datasetPath = "/datasets/$Dataset"
$trainPath = "$datasetPath/split/split=train"

function Invoke-Dc([string[]]$DockerArgs) {
    # Java CLI can print normal diagnostics to stderr. Inspect exit status explicitly.
    $ErrorActionPreference = 'Continue'
    $PSNativeCommandUseErrorActionPreference = $false
    # Windows PowerShell 5.1 wraps stderr as ErrorRecord. Convert it before display.
    $lines = @(& docker compose @composePrefix @DockerArgs 2>&1 | ForEach-Object { $_.ToString() })
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw ("Docker command failed ($code): " + ($lines -join "`n")) }
    return ($lines | ForEach-Object { "$_" })
}

function Save-State([string]$Prefix) {
    Start-Sleep -Seconds 5
    Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','du','-s',$trainPath) |
        Out-File "$resultDir/$Prefix-cache.txt" -Encoding utf8
    Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fsadmin','report','metrics') |
        Out-File "$resultDir/$Prefix-metrics.txt" -Encoding utf8
}

function Assert-Cache([bool]$Warm) {
    for ($attempt=0; $attempt -lt 15; $attempt++) {
        $lines = @(Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fs','du','-s',$trainPath))
        $rows = @($lines | Where-Object { $_ -match '^\s*\d+\s+\d+\s+\(' })
        if ($rows.Count -ne 1 -or $rows[0] -notmatch '^\s*(\d+)\s+(\d+)\s+\(') {
            throw ('Cannot parse cache bytes: ' + ($lines -join "`n"))
        }
        $size = [long]$Matches[1]; $cached = [long]$Matches[2]
        if ($size -le 0) { throw 'Train directory is empty.' }
        if (($Warm -and $cached -eq $size) -or ((-not $Warm) -and $cached -eq 0)) {
            Write-Host "Train cache: $cached / $size bytes"
            return
        }
        Start-Sleep -Seconds 2
    }
    throw "Cache state not ready after polling: $cached / $size; warm=$Warm"
}

function Run-Trial([string]$Label, [int]$Repeat) {
    $data = if ($Label -eq 'H0') { "hdfs://hdfs-storage:9000$datasetPath" } else { "alluxio://alluxio-master:19998$datasetPath" }
    Save-State "$Label-$Repeat-before"
    Write-Host "Starting $Label repeat $Repeat / $Repeats"
    $argsList = @('--profile','jobs','run','--rm','spark','bash','scripts/spark.sh','src/benchmark_als.py',
        '--data',$data,'--manifest',$manifest,'--label',$Label,'--repeat',"$Repeat",'--results',$resultDir,
        '--rank','20','--reg','0.1','--max-iter','10','--num-blocks','16','--seed','42')
    $ErrorActionPreference = 'Continue'
    $PSNativeCommandUseErrorActionPreference = $false
    & docker compose @composePrefix @argsList 2>&1 |
        ForEach-Object { $_.ToString() } |
        Tee-Object -FilePath "$resultDir/$Label-$Repeat-spark.log"
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw "ALS trial $Label-$Repeat failed ($code). See its spark.log; stop this experiment." }
    Save-State "$Label-$Repeat-after"
}

# HDFS runs on machine A. Validate metadata AND real DataNode reads from Spark on B.
Invoke-Dc -DockerArgs @('--profile','jobs','run','--rm','spark','bash','scripts/spark.sh','twohost/check_storage.py',
    '--dataset',$Dataset,'--manifest',$manifest) | Out-Host
Invoke-Dc -DockerArgs @('exec','alluxio-master','alluxio','fsadmin','report','capacity') |
    Out-File "$resultDir/capacity-before.txt" -Encoding utf8
Invoke-Dc -DockerArgs @('config') | Out-File "$resultDir/compose-effective.txt" -Encoding utf8
Invoke-Dc -DockerArgs @('version') | Out-File "$resultDir/compose-version.txt" -Encoding utf8
$settings = [ordered]@{dataset=$Dataset; repeats=$Repeats; runName=$RunName; rank=20; regParam=0.1;
    maxIter=10; numUserBlocks=16; numItemBlocks=16; seed=42;
    input=$trainPath; sparkCache='MEMORY_AND_DISK'; osCache='uncontrolled';
    topology='two physical hosts; HDFS on A; Spark+Alluxio on B'; storageIp=$storageAddress;
    note='Benchmark read+fixed ALS only; no validation, test, model export or web; RPC cache access baseline.'}
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
