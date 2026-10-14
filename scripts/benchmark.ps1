param([string]$Dataset="demo-v1", [int]$Repeats=5)
$ErrorActionPreference="Stop"
function Dc { & docker compose @args; if ($LASTEXITCODE -ne 0) { throw "Docker command failed" } }
$path="/datasets/$Dataset/ratings"
$version=(Get-Content "data/processed/$Dataset/manifest.json" -Raw | ConvertFrom-Json).datasetVersion
New-Item -ItemType Directory -Force "experiments/results" | Out-Null
foreach ($task in @("scan","group")) {
  for ($r=1; $r -le $Repeats; $r++) {
    # Rotate direct/cold/warm blocks to reduce simple execution-order bias.
    $blocks=if ($r % 2 -eq 1) { @("direct","alluxio") } else { @("alluxio","direct") }
    foreach ($block in $blocks) {
      if ($block -eq "direct") {
        Dc --profile jobs run --rm spark bash scripts/spark.sh src/benchmark.py --input "hdfs://namenode:9000$path" --label H0 --repeat $r --task $task --dataset-version $version
      } else {
        Dc exec alluxio-master alluxio fs free -f $path
        Dc exec alluxio-master alluxio fs du $path | Out-File "experiments/results/$task-H1-$r-cache-before.txt"
        Dc exec alluxio-master alluxio fsadmin report metrics | Out-File "experiments/results/$task-H1-$r-metrics-before.txt"
        Dc --profile jobs run --rm spark bash scripts/spark.sh src/benchmark.py --input "alluxio://alluxio-master:19998$path" --label H1 --repeat $r --task $task --dataset-version $version
        Dc exec alluxio-master alluxio fsadmin report metrics | Out-File "experiments/results/$task-H1-$r-metrics-after.txt"
        Dc exec alluxio-master alluxio fs load $path
        Dc exec alluxio-master alluxio fs du $path | Out-File "experiments/results/$task-H2-$r-cache-before.txt"
        Dc exec alluxio-master alluxio fsadmin report metrics | Out-File "experiments/results/$task-H2-$r-metrics-before.txt"
        Dc --profile jobs run --rm spark bash scripts/spark.sh src/benchmark.py --input "alluxio://alluxio-master:19998$path" --label H2 --repeat $r --task $task --dataset-version $version
        Dc exec alluxio-master alluxio fsadmin report metrics | Out-File "experiments/results/$task-H2-$r-metrics-after.txt"
      }
    }
  }
}
Dc --profile jobs run --rm spark python3 scripts/summarize.py
