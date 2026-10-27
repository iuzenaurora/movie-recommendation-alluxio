param(
    [int]$Users = 500,
    [int]$Requests = 50,
    [int]$Repeats = 3,
    [string]$RunName = ('report-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
)
$ErrorActionPreference = 'Stop'
if ($RunName -notmatch '^[A-Za-z0-9_-]+$' -or $Users -lt 1 -or $Requests -lt 1 -or $Repeats -lt 1) {
    throw 'RunName must be a simple name; Users, Requests and Repeats must be positive.'
}
$ProjectRoot = Split-Path $PSScriptRoot -Parent
Push-Location $ProjectRoot
$Compose = @('compose','-p','movielab-evaluation','-f','compose.evaluation.yaml')
$Output = 'experiments/recommendation-evaluation/' + $RunName
function Invoke-EvaluationDocker {
    param([string[]]$Arguments)
    & docker @Compose @Arguments
    if ($LASTEXITCODE -ne 0) { throw ('Docker evaluation failed: ' + ($Arguments -join ' ')) }
}
try {
    if (Test-Path -LiteralPath $Output) { throw 'Result directory exists. Choose a new RunName.' }
    Invoke-EvaluationDocker -Arguments @('build','evaluator','api')
    Invoke-EvaluationDocker -Arguments @('run','--rm','--no-deps','evaluator','python','scripts/evaluate_recommendations.py',
        '--users',"$Users",'--out',$Output)
    Invoke-EvaluationDocker -Arguments @('up','-d','--wait','--wait-timeout','300','mongo','api')
    $DockerInfo = & docker info --format '{{json .}}' | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw 'Cannot read Docker environment' }
    $ApiContainer = & docker @Compose ps -q api
    $Inspection = (& docker inspect $ApiContainer | ConvertFrom-Json)[0]
    $Evidence = [ordered]@{
        serverVersion=$DockerInfo.ServerVersion; cpus=$DockerInfo.NCPU; dockerMemoryBytes=$DockerInfo.MemTotal
        operatingSystem=$DockerInfo.OperatingSystem; kernel=$DockerInfo.KernelVersion; architecture=$DockerInfo.Architecture
        apiImage=$Inspection.Image; apiMemoryLimitBytes=$Inspection.HostConfig.Memory
        apiNanoCpus=$Inspection.HostConfig.NanoCpus; note='Docker VM resources; not a claim about physical host RAM'
    }
    $Evidence | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath ($Output+'/environment.json') -Encoding utf8
    Invoke-EvaluationDocker -Arguments @('run','--rm','--no-deps','evaluator','python','scripts/check_serving_parity.py','--results',$Output)
    Invoke-EvaluationDocker -Arguments @('run','--rm','--no-deps','evaluator','python','scripts/benchmark_recommendation_api.py',
        '--quality',$Output,'--out',($Output+'/api'),'--requests',"$Requests",'--repeats',"$Repeats")
    Invoke-EvaluationDocker -Arguments @('run','--rm','--no-deps','evaluator','python','scripts/plot_recommendation_report.py','--results',$Output)
    Invoke-EvaluationDocker -Arguments @('run','--rm','--no-deps','evaluator','python','scripts/summarize_recommendation_report.py','--results',$Output)
    Write-Host ('Completed: ' + $Output + '/complete-report.md')
} finally {
    # Only the dedicated evaluation stack is stopped; application services are untouched.
    & docker @Compose stop api mongo
    Pop-Location
}
