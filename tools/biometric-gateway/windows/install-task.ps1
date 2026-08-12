param(
    [Parameter(Mandatory = $true)]
    [string]$PhpPath,

    [Parameter(Mandatory = $true)]
    [string]$ConfigPath,

    [string]$TaskName = "SchoolLift Biometric Gateway",
    [string]$TaskUser = "$env:USERDOMAIN\$env:USERNAME",
    [System.Management.Automation.PSCredential]$Credential
)

$ErrorActionPreference = "Stop"
$GatewayRoot = Split-Path -Parent $PSScriptRoot
$GatewayScript = Join-Path $GatewayRoot "bin\gateway.php"

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "php.exe was not found at: $PhpPath"
}
if (-not (Test-Path -LiteralPath $ConfigPath -PathType Leaf)) {
    throw "Gateway configuration was not found at: $ConfigPath"
}
if (-not (Test-Path -LiteralPath $GatewayScript -PathType Leaf)) {
    throw "Gateway script was not found at: $GatewayScript"
}

$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
$ConfigPath = (Resolve-Path -LiteralPath $ConfigPath).Path
$GatewayScript = (Resolve-Path -LiteralPath $GatewayScript).Path

$Arguments = '"{0}" once --config="{1}"' -f $GatewayScript, $ConfigPath
$Action = New-ScheduledTaskAction -Execute $PhpPath -Argument $Arguments -WorkingDirectory $GatewayRoot
$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)
$Settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5) `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1)
if ($null -eq $Credential) {
    $Credential = Get-Credential -UserName $TaskUser -Message "Credentials for the restricted SchoolLift gateway account"
}
$TaskUser = $Credential.UserName
$TaskPassword = $Credential.GetNetworkCredential().Password

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $Action `
    -Trigger $Trigger `
    -Settings $Settings `
    -User $TaskUser `
    -Password $TaskPassword `
    -RunLevel Limited `
    -Description "Polls one bidirectional ZKBio terminal and sends attendance events to SchoolLift." `
    -Force | Out-Null

Write-Host "Installed scheduled task: $TaskName"
Write-Host "Run it once from Task Scheduler, then inspect gateway status and log before leaving the site."
