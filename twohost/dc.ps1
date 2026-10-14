param(
    [Parameter(Mandatory=$true)][ValidateSet('storage','compute')][string]$Role,
    [Parameter(Mandatory=$true)][string[]]$DockerArgs
)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Push-Location $root
try {
    $envFile = "twohost/.env.$Role"
    if (-not (Test-Path $envFile)) { throw "Copy and edit $envFile.example first." }
    $prefix = @('--env-file',$envFile,'-f',"twohost/compose.$Role.yaml")
    $ErrorActionPreference = 'Continue'
    $PSNativeCommandUseErrorActionPreference = $false
    & docker compose @prefix @DockerArgs 2>&1 | ForEach-Object { $_.ToString() }
    $code = $LASTEXITCODE
    if ($code -ne 0) { throw "Docker compose failed ($code)." }
} finally { Pop-Location }
