param(
    [string]$SourceEnvFile = "artifacts/.prod.env",
    [string]$DumpFile = "artifacts/migration/prod.sql",
    [string]$TargetDbHost = $env:LEAN_DB_HOST,
    [string]$TargetDbUser = $env:LEAN_DB_USER,
    [string]$TargetDbPassword = $env:LEAN_DB_PASSWORD,
    [string]$TargetDbName = $env:LEAN_DB_DATABASE,
    [string]$TargetDbPort = $env:LEAN_DB_PORT,
    [switch]$SkipDump,
    [switch]$SkipImport,
    [switch]$SkipMigrate,
    [switch]$Execute
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Parse-EnvFile {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        throw "Env file not found: $Path"
    }

    $map = @{}
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq "" -or $line.StartsWith("#")) {
            return
        }

        if ($line -notmatch "^[A-Za-z_][A-Za-z0-9_]*\s*=") {
            return
        }

        $parts = $line -split "=", 2
        $key = $parts[0].Trim()
        $value = $parts[1].Trim()

        if ($value.StartsWith("'") -and $value.EndsWith("'")) {
            $value = $value.Substring(1, $value.Length - 2)
        } elseif ($value.StartsWith('"') -and $value.EndsWith('"')) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        if ($value -match "^(.*?)\s+#") {
            $value = $Matches[1].Trim()
        }

        $map[$key] = $value
    }

    return $map
}

function Require-Value {
    param(
        [string]$Name,
        [string]$Value
    )
    if ([string]::IsNullOrWhiteSpace($Value)) {
        throw "Missing required value: $Name"
    }
}

function Invoke-External {
    param(
        [string]$FilePath,
        [string[]]$Arguments
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed (exit $LASTEXITCODE): $FilePath"
    }
}

$sourceDbHost = $env:LEAN_OLD_DB_HOST
$sourceDbUser = $env:LEAN_OLD_DB_USER
$sourceDbPassword = $env:LEAN_OLD_DB_PASSWORD
$sourceDbName = $env:LEAN_OLD_DB_DATABASE
$sourceDbPort = if ([string]::IsNullOrWhiteSpace($env:LEAN_OLD_DB_PORT)) { "3306" } else { "$($env:LEAN_OLD_DB_PORT)" }

if (
    [string]::IsNullOrWhiteSpace($sourceDbHost) -or
    [string]::IsNullOrWhiteSpace($sourceDbUser) -or
    [string]::IsNullOrWhiteSpace($sourceDbPassword) -or
    [string]::IsNullOrWhiteSpace($sourceDbName)
) {
    if (Test-Path $SourceEnvFile) {
        $source = Parse-EnvFile -Path $SourceEnvFile
        if ([string]::IsNullOrWhiteSpace($sourceDbHost)) { $sourceDbHost = "$($source['LEAN_DB_HOST'])" }
        if ([string]::IsNullOrWhiteSpace($sourceDbUser)) { $sourceDbUser = "$($source['LEAN_DB_USER'])" }
        if ([string]::IsNullOrWhiteSpace($sourceDbPassword)) { $sourceDbPassword = "$($source['LEAN_DB_PASSWORD'])" }
        if ([string]::IsNullOrWhiteSpace($sourceDbName)) { $sourceDbName = "$($source['LEAN_DB_DATABASE'])" }
        if ($sourceDbPort -eq "3306") {
            $fromFilePort = "$($source['LEAN_DB_PORT'])"
            if (-not [string]::IsNullOrWhiteSpace($fromFilePort)) {
                $sourceDbPort = $fromFilePort
            }
        }
    }
}

$targetDbPort = if ([string]::IsNullOrWhiteSpace($TargetDbPort)) { "3306" } else { "$TargetDbPort" }

Require-Value -Name "LEAN_OLD_DB_HOST (source)" -Value $sourceDbHost
Require-Value -Name "LEAN_OLD_DB_USER (source)" -Value $sourceDbUser
Require-Value -Name "LEAN_OLD_DB_PASSWORD (source)" -Value $sourceDbPassword
Require-Value -Name "LEAN_OLD_DB_DATABASE (source)" -Value $sourceDbName

Require-Value -Name "TargetDbHost or LEAN_DB_HOST" -Value $TargetDbHost
Require-Value -Name "TargetDbUser or LEAN_DB_USER" -Value $TargetDbUser
Require-Value -Name "TargetDbPassword or LEAN_DB_PASSWORD" -Value $TargetDbPassword
Require-Value -Name "TargetDbName or LEAN_DB_DATABASE" -Value $TargetDbName

$dumpDirectory = Split-Path -Parent $DumpFile
if (-not [string]::IsNullOrWhiteSpace($dumpDirectory) -and -not (Test-Path $dumpDirectory)) {
    New-Item -ItemType Directory -Path $dumpDirectory -Force | Out-Null
}

Write-Host "Cutover plan"
Write-Host "  Source DB: ${sourceDbHost}:$sourceDbPort / $sourceDbName"
Write-Host "  Target DB: ${TargetDbHost}:$targetDbPort / $TargetDbName"
Write-Host "  Dump file: $DumpFile"
Write-Host "  Execute:   $Execute"
Write-Host ""

if (-not $SkipDump) {
    $dumpArgs = @(
        "--single-transaction",
        "--quick",
        "--set-gtid-purged=OFF",
        "--column-statistics=0",
        "--host=$sourceDbHost",
        "--port=$sourceDbPort",
        "--user=$sourceDbUser",
        "--password=$sourceDbPassword",
        $sourceDbName,
        "--result-file=$DumpFile"
    )

    Write-Host "Step 1: Export source DB"
    if ($Execute) {
        Invoke-External -FilePath "mysqldump" -Arguments $dumpArgs
    } else {
        Write-Host "  [dry-run] mysqldump ..."
    }
}

if (-not $SkipImport) {
    $importArgs = @(
        "--host=$TargetDbHost",
        "--port=$targetDbPort",
        "--user=$TargetDbUser",
        "--password=$TargetDbPassword",
        $TargetDbName,
        "-e",
        "source $DumpFile"
    )

    Write-Host "Step 2: Import dump into target DB"
    if ($Execute) {
        Invoke-External -FilePath "mysql" -Arguments $importArgs
    } else {
        Write-Host "  [dry-run] mysql ... source $DumpFile"
    }
}

if (-not $SkipMigrate) {
    Write-Host "Step 3: Run Leantime db:migrate against target"
    if ($Execute) {
        Invoke-External -FilePath "php" -Arguments @("bin/leantime", "db:migrate")
    } else {
        Write-Host "  [dry-run] php bin/leantime db:migrate"
    }
}

Write-Host ""
Write-Host "Done."
if (-not $Execute) {
    Write-Host "Re-run with -Execute to perform the migration."
}
