#requires -Version 5.1

[CmdletBinding()]
param(
    [switch]$Elevated
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Test-IsAdministrator {
    $Identity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    $Principal = New-Object System.Security.Principal.WindowsPrincipal($Identity)
    return $Principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-IsAdministrator)) {
    try {
        $PowerShell = Join-Path $env:SystemRoot "System32\WindowsPowerShell\v1.0\powershell.exe"
        $SafeScriptPath = $PSCommandPath.Replace('"', '')
        $Arguments = '-NoLogo -NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File "{0}" -Elevated' -f $SafeScriptPath
        Start-Process -FilePath $PowerShell -ArgumentList $Arguments -Verb RunAs | Out-Null
    } catch {
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.MessageBox]::Show(
            "Administrator approval is required to protect the configuration and install the background task.",
            "SchoolLift Gateway Manager",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Error
        ) | Out-Null
    }
    exit
}

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$script:TaskName = "SchoolLift Biometric Gateway"
$script:GatewayRoot = (Resolve-Path -LiteralPath (Split-Path -Parent $PSScriptRoot)).Path
$script:GatewayScript = Join-Path $script:GatewayRoot "bin\gateway.php"
$script:InstallTaskScript = Join-Path $PSScriptRoot "install-task.ps1"
$script:UninstallTaskScript = Join-Path $PSScriptRoot "uninstall-task.ps1"
$script:DataRoot = Join-Path $env:ProgramData "SchoolLift\Biometric"
$script:RuntimeRoot = Join-Path $script:DataRoot "runtime"
$script:ConfigPath = Join-Path $script:DataRoot "gateway-config.json"
$script:LogPath = Join-Path $script:RuntimeRoot "gateway.log"
$script:PhpPath = $null
$script:GatewayId = $null

function Assert-FixedInstallationFiles {
    foreach ($Path in @($script:GatewayScript, $script:InstallTaskScript, $script:UninstallTaskScript)) {
        if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
            throw "A required SchoolLift gateway file is missing: $Path"
        }
    }
    if ($script:GatewayRoot -match '(?i)\\(?:inetpub\\wwwroot|xampp\\htdocs|wamp64\\www|laragon\\www)(?:\\|$)') {
        throw "Move the biometric-gateway folder outside the web-server document folder before installing it."
    }
    if ($script:GatewayRoot.StartsWith("\\")) {
        throw "Install the biometric gateway on a fixed local Windows drive, not a network share."
    }
}

function Get-AccountSid {
    param([Parameter(Mandatory = $true)][string]$Value)
    return New-Object System.Security.Principal.SecurityIdentifier($Value)
}

function New-DirectoryRule {
    param(
        [Parameter(Mandatory = $true)][System.Security.Principal.SecurityIdentifier]$Identity,
        [Parameter(Mandatory = $true)][System.Security.AccessControl.FileSystemRights]$Rights
    )
    return New-Object System.Security.AccessControl.FileSystemAccessRule(
        $Identity,
        $Rights,
        [System.Security.AccessControl.InheritanceFlags]"ContainerInherit, ObjectInherit",
        [System.Security.AccessControl.PropagationFlags]::None,
        [System.Security.AccessControl.AccessControlType]::Allow
    )
}

function New-FileRule {
    param(
        [Parameter(Mandatory = $true)][System.Security.Principal.SecurityIdentifier]$Identity,
        [Parameter(Mandatory = $true)][System.Security.AccessControl.FileSystemRights]$Rights
    )
    return New-Object System.Security.AccessControl.FileSystemAccessRule(
        $Identity,
        $Rights,
        [System.Security.AccessControl.AccessControlType]::Allow
    )
}

function Protect-GatewayStorage {
    [System.IO.Directory]::CreateDirectory($script:DataRoot) | Out-Null
    [System.IO.Directory]::CreateDirectory($script:RuntimeRoot) | Out-Null

    $Administrators = Get-AccountSid "S-1-5-32-544"
    $System = Get-AccountSid "S-1-5-18"
    $LocalService = Get-AccountSid "S-1-5-19"

    $RootAcl = New-Object System.Security.AccessControl.DirectorySecurity
    $RootAcl.SetAccessRuleProtection($true, $false)
    $RootAcl.AddAccessRule((New-DirectoryRule $Administrators ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $RootAcl.AddAccessRule((New-DirectoryRule $System ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $RootAcl.AddAccessRule((New-DirectoryRule $LocalService ([System.Security.AccessControl.FileSystemRights]::ReadAndExecute)))
    [System.IO.Directory]::SetAccessControl($script:DataRoot, $RootAcl)

    $RuntimeAcl = New-Object System.Security.AccessControl.DirectorySecurity
    $RuntimeAcl.SetAccessRuleProtection($true, $false)
    $RuntimeAcl.AddAccessRule((New-DirectoryRule $Administrators ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $RuntimeAcl.AddAccessRule((New-DirectoryRule $System ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $RuntimeAcl.AddAccessRule((New-DirectoryRule $LocalService ([System.Security.AccessControl.FileSystemRights]::Modify)))
    [System.IO.Directory]::SetAccessControl($script:RuntimeRoot, $RuntimeAcl)

}

function Protect-GatewayCode {
    # LOCAL SERVICE must never execute gateway code that an ordinary local user
    # can replace. Production MSI packaging naturally gets this protection from
    # Program Files; the source-tree installer applies the equivalent ACL here.
    $ReparsePoint = Get-ChildItem -LiteralPath $script:GatewayRoot -Recurse -Force -ErrorAction Stop |
        Where-Object { ($_.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0 } |
        Select-Object -First 1
    if ($null -ne $ReparsePoint) {
        throw "The gateway code contains a symbolic link or reparse point and cannot be secured safely: $($ReparsePoint.FullName)"
    }

    $Administrators = Get-AccountSid "S-1-5-32-544"
    $System = Get-AccountSid "S-1-5-18"
    $LocalService = Get-AccountSid "S-1-5-19"
    $DirectoryAcl = New-Object System.Security.AccessControl.DirectorySecurity
    $DirectoryAcl.SetAccessRuleProtection($true, $false)
    $DirectoryAcl.AddAccessRule((New-DirectoryRule $Administrators ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $DirectoryAcl.AddAccessRule((New-DirectoryRule $System ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $DirectoryAcl.AddAccessRule((New-DirectoryRule $LocalService ([System.Security.AccessControl.FileSystemRights]::ReadAndExecute)))

    $FileAcl = New-Object System.Security.AccessControl.FileSecurity
    $FileAcl.SetAccessRuleProtection($true, $false)
    $FileAcl.AddAccessRule((New-FileRule $Administrators ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $FileAcl.AddAccessRule((New-FileRule $System ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $FileAcl.AddAccessRule((New-FileRule $LocalService ([System.Security.AccessControl.FileSystemRights]::ReadAndExecute)))

    [System.IO.Directory]::SetAccessControl($script:GatewayRoot, $DirectoryAcl)
    foreach ($Item in Get-ChildItem -LiteralPath $script:GatewayRoot -Recurse -Force -ErrorAction Stop) {
        if ($Item.PSIsContainer) {
            [System.IO.Directory]::SetAccessControl($Item.FullName, $DirectoryAcl)
        } else {
            [System.IO.File]::SetAccessControl($Item.FullName, $FileAcl)
        }
    }
}

function Protect-ConfigFile {
    if (-not (Test-Path -LiteralPath $script:ConfigPath -PathType Leaf)) {
        return
    }
    $Administrators = Get-AccountSid "S-1-5-32-544"
    $System = Get-AccountSid "S-1-5-18"
    $LocalService = Get-AccountSid "S-1-5-19"
    $FileAcl = New-Object System.Security.AccessControl.FileSecurity
    $FileAcl.SetAccessRuleProtection($true, $false)
    $FileAcl.AddAccessRule((New-FileRule $Administrators ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $FileAcl.AddAccessRule((New-FileRule $System ([System.Security.AccessControl.FileSystemRights]::FullControl)))
    $FileAcl.AddAccessRule((New-FileRule $LocalService ([System.Security.AccessControl.FileSystemRights]::ReadAndExecute)))
    [System.IO.File]::SetAccessControl($script:ConfigPath, $FileAcl)
}

function Find-CompatiblePhp {
    $Candidates = New-Object System.Collections.Generic.List[string]
    foreach ($Candidate in @(
        (Join-Path $script:GatewayRoot "runtime\php.exe"),
        "C:\SchoolLift\PHP82\php.exe",
        "C:\PHP82\php.exe",
        "C:\php\php.exe"
    )) {
        $Candidates.Add($Candidate)
    }
    $PathPhp = Get-Command php.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -ne $PathPhp -and $PathPhp.CommandType -eq "Application") {
        $Candidates.Add($PathPhp.Source)
    }

    $Seen = @{}
    foreach ($Candidate in $Candidates) {
        if ([string]::IsNullOrWhiteSpace($Candidate) -or $Seen.ContainsKey($Candidate)) {
            continue
        }
        $Seen[$Candidate] = $true
        if (-not (Test-Path -LiteralPath $Candidate -PathType Leaf)) {
            continue
        }
        try {
            $Version = & $Candidate -r "echo PHP_VERSION_ID;" 2>$null
            if ($LASTEXITCODE -eq 0 -and [int]$Version -ge 80200) {
                return (Resolve-Path -LiteralPath $Candidate).Path
            }
        } catch {
            continue
        }
    }
    return $null
}

function Get-JsonProperty {
    param(
        [AllowNull()][object]$Object,
        [Parameter(Mandatory = $true)][string]$Name,
        [AllowNull()][object]$Default = $null
    )
    if ($null -eq $Object) {
        return $Default
    }
    $Property = $Object.PSObject.Properties[$Name]
    return $(if ($null -eq $Property) { $Default } else { $Property.Value })
}

function Read-ManagerConfiguration {
    if (-not (Test-Path -LiteralPath $script:ConfigPath -PathType Leaf)) {
        return $null
    }
    $Length = (Get-Item -LiteralPath $script:ConfigPath).Length
    if ($Length -gt 131072) {
        throw "The existing gateway configuration is larger than the 128 KiB safety limit."
    }
    return Get-Content -LiteralPath $script:ConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json
}

function Test-AbsoluteUrl {
    param(
        [Parameter(Mandatory = $true)][string]$Value,
        [Parameter(Mandatory = $true)][string]$Label,
        [switch]$AllowLoopbackHttp
    )
    $Uri = $null
    if (-not [System.Uri]::TryCreate($Value, [System.UriKind]::Absolute, [ref]$Uri)) {
        throw "$Label must be a complete URL."
    }
    if (-not [string]::IsNullOrEmpty($Uri.UserInfo) -or -not [string]::IsNullOrEmpty($Uri.Query) -or -not [string]::IsNullOrEmpty($Uri.Fragment)) {
        throw "$Label must not contain credentials, a query, or a fragment."
    }
    if ($Uri.Scheme -eq "https") {
        return $Uri
    }
    $Loopback = @("127.0.0.1", "localhost", "::1") -contains $Uri.Host.ToLowerInvariant()
    if ($AllowLoopbackHttp -and $Uri.Scheme -eq "http" -and $Loopback) {
        return $Uri
    }
    if ($AllowLoopbackHttp) {
        throw "$Label must use HTTPS. HTTP is allowed only for ZKBio on this same computer (127.0.0.1)."
    }
    throw "$Label must use HTTPS."
}

function Save-ManagerConfiguration {
    $SchoolLiftValue = $script:SchoolLiftText.Text.Trim().TrimEnd('/')
    $ProviderValue = $script:ProviderText.Text.Trim().TrimEnd('/')
    $Serial = $script:SerialText.Text.Trim()
    $ProviderUsername = $script:UsernameText.Text.Trim()
    $ProviderPassword = $script:PasswordText.Text
    $BearerToken = $script:TokenText.Text.Trim()

    $SchoolLiftUri = Test-AbsoluteUrl $SchoolLiftValue "SchoolLift address"
    $ProviderUri = Test-AbsoluteUrl $ProviderValue "ZKBio address" -AllowLoopbackHttp
    if ($Serial -notmatch '^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$') {
        throw "Terminal serial must contain only letters, numbers, dot, underscore, colon, or hyphen."
    }
    if ($Serial -in @("SIM-IN-001", "SIM-OUT-001")) {
        throw "Use one bidirectional terminal serial, not a separate IN or OUT serial."
    }
    if ([string]::IsNullOrWhiteSpace($ProviderUsername) -or [string]::IsNullOrEmpty($ProviderPassword)) {
        throw "Enter the read-only ZKBio API username and password."
    }
    if ($BearerToken.Length -lt 32 -or $BearerToken -match '[\x00-\x1F\x7F]') {
        throw "Paste the high-entropy integration token shown once by SchoolLift."
    }
    if ([string]::IsNullOrWhiteSpace($script:GatewayId)) {
        $script:GatewayId = "schoollift-" + ([Guid]::NewGuid().ToString("N").Substring(0, 20))
    }

    Protect-GatewayStorage
    $AllowLocalHttp = $ProviderUri.Scheme -eq "http"
    $Configuration = [ordered]@{
        gateway_id = $script:GatewayId
        timezone = "Africa/Lagos"
        database_path = Join-Path $script:RuntimeRoot "gateway.sqlite"
        lock_path = Join-Path $script:RuntimeRoot "gateway.lock"
        log_path = $script:LogPath
        log_to_stdout = $false
        verify_tls = $true
        allow_insecure_localhost = $AllowLocalHttp
        request_timeout_seconds = 20
        provider = [ordered]@{
            base_url = $ProviderValue
            username = $ProviderUsername
            password = $ProviderPassword
            auth_path = "/api-token-auth/"
            auth_body_format = "json"
            token_field = "token"
            authorization_scheme = "Token"
            transactions_path = "/iclock/api/transactions/"
            data_field = "data"
            terminal_serial = $Serial
            verification_method_map = [ordered]@{
                "0" = "pin"
                "1" = "fingerprint"
                "2" = "card"
                "4" = "face"
                "15" = "face"
            }
            page_size = 100
            max_pages = 50
            overlap_seconds = 172800
            initial_lookback_seconds = 172800
        }
        schoollift = [ordered]@{
            base_url = $SchoolLiftValue
            bearer_token = $BearerToken
            events_path = "/api/biometric/v2/events"
            health_path = "/api/biometric/v2/health"
            control_poll_path = "/api/biometric/v2/gateway/poll"
            control_result_path = "/api/biometric/v2/gateway/result"
            batch_size = 100
            max_batches_per_run = 10
        }
        retry = [ordered]@{
            base_seconds = 5
            maximum_seconds = 900
            maximum_attempts = 12
            provider_base_seconds = 15
            provider_maximum_seconds = 900
        }
        delivered_retention_days = 30
    }

    $Json = $Configuration | ConvertTo-Json -Depth 8
    $TemporaryPath = Join-Path $script:DataRoot ("gateway-config-" + [Guid]::NewGuid().ToString("N") + ".tmp")
    $Utf8 = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllText($TemporaryPath, $Json, $Utf8)
        Move-Item -LiteralPath $TemporaryPath -Destination $script:ConfigPath -Force
        Protect-ConfigFile
    } finally {
        if (Test-Path -LiteralPath $TemporaryPath) {
            Remove-Item -LiteralPath $TemporaryPath -Force
        }
    }
    Write-Activity "Configuration saved securely" "Saved the non-executable JSON configuration to $($script:ConfigPath). Secrets were not placed in the task arguments."
    return $Configuration
}

function Invoke-FixedGatewayAction {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet("doctor", "once", "status", "retry-dead")]
        [string]$Action
    )
    if ([string]::IsNullOrWhiteSpace($script:PhpPath)) {
        throw "PHP 8.2 with the required extensions was not found in a supported location."
    }
    if (-not (Test-Path -LiteralPath $script:ConfigPath -PathType Leaf)) {
        throw "Save the gateway configuration first."
    }
    $Output = @(& $script:PhpPath $script:GatewayScript $Action "--config=$($script:ConfigPath)" 2>&1 | ForEach-Object { "$_" })
    $ExitCode = $LASTEXITCODE
    $Text = ($Output -join [Environment]::NewLine).Trim()
    Write-Activity ("Gateway " + $Action) ($(if ($Text -eq "") { "No output." } else { $Text }))
    return [PSCustomObject]@{
        ExitCode = $ExitCode
        Text = $Text
    }
}

function Test-TaskDefinition {
    param([AllowNull()][object]$Task)
    if ($null -eq $Task) {
        return $false
    }
    $Action = @($Task.Actions)[0]
    if ($null -eq $Action) {
        return $false
    }
    $ExpectedArguments = '"{0}" once --config="{1}"' -f $script:GatewayScript, $script:ConfigPath
    if ($Action.Execute -ine $script:PhpPath -or
        $Action.Arguments -ine $ExpectedArguments -or
        $Action.WorkingDirectory -ine $script:GatewayRoot) {
        return $false
    }
    try {
        if ($Task.Principal.UserId -eq "S-1-5-19") {
            return $true
        }
        $PrincipalAccount = New-Object System.Security.Principal.NTAccount($Task.Principal.UserId)
        $PrincipalSid = $PrincipalAccount.Translate([System.Security.Principal.SecurityIdentifier]).Value
        return $PrincipalSid -eq "S-1-5-19"
    } catch {
        return $false
    }
}

function Get-TaskStateText {
    $Task = Get-ScheduledTask -TaskName $script:TaskName -ErrorAction SilentlyContinue
    if ($null -eq $Task) {
        return "Not installed"
    }
    $Info = Get-ScheduledTaskInfo -TaskName $script:TaskName
    $Matches = Test-TaskDefinition $Task
    $Integrity = $(if ($Matches) { "verified" } else { "WARNING: task action differs from this manager" })
    return "State: $($Task.State); last result: $($Info.LastTaskResult); last run: $($Info.LastRunTime); $Integrity"
}

function Refresh-TaskState {
    try {
        $script:TaskStateLabel.Text = "Background task: " + (Get-TaskStateText)
    } catch {
        $script:TaskStateLabel.Text = "Background task: unable to read status - $($_.Exception.Message)"
    }
}

function Write-Activity {
    param(
        [Parameter(Mandatory = $true)][string]$Title,
        [Parameter(Mandatory = $true)][string]$Message
    )
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $script:ActivityText.AppendText("[$Timestamp] $Title`r`n$Message`r`n`r`n")
    $script:ActivityText.SelectionStart = $script:ActivityText.TextLength
    $script:ActivityText.ScrollToCaret()
    [System.Windows.Forms.Application]::DoEvents()
}

function Show-ErrorMessage {
    param([Parameter(Mandatory = $true)][System.Exception]$Error)
    Write-Activity "Operation failed" $Error.Message
    [System.Windows.Forms.MessageBox]::Show(
        $Error.Message,
        "SchoolLift Gateway Manager",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
}

function Add-InputField {
    param(
        [Parameter(Mandatory = $true)][System.Windows.Forms.Control]$Parent,
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][int]$Top,
        [switch]$Password
    )
    $FieldLabel = New-Object System.Windows.Forms.Label
    $FieldLabel.Text = $Label
    $FieldLabel.Location = New-Object System.Drawing.Point(18, ($Top + 4))
    $FieldLabel.Size = New-Object System.Drawing.Size(205, 23)
    $Parent.Controls.Add($FieldLabel)

    $TextBox = New-Object System.Windows.Forms.TextBox
    $TextBox.Location = New-Object System.Drawing.Point(228, $Top)
    $TextBox.Size = New-Object System.Drawing.Size(610, 25)
    $TextBox.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Left, Right"
    if ($Password) {
        $TextBox.UseSystemPasswordChar = $true
    }
    $Parent.Controls.Add($TextBox)
    return $TextBox
}

try {
    Assert-FixedInstallationFiles
    Protect-GatewayStorage
    $script:PhpPath = Find-CompatiblePhp
} catch {
    [System.Windows.Forms.MessageBox]::Show(
        $_.Exception.Message,
        "SchoolLift Gateway Manager",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
    exit 1
}

$Form = New-Object System.Windows.Forms.Form
$Form.Text = "SchoolLift Biometric Gateway Manager"
$Form.StartPosition = "CenterScreen"
$Form.Size = New-Object System.Drawing.Size(930, 790)
$Form.MinimumSize = New-Object System.Drawing.Size(820, 720)
$Form.Font = New-Object System.Drawing.Font("Segoe UI", 9)
$Form.AutoScaleMode = [System.Windows.Forms.AutoScaleMode]::Dpi

$TitleLabel = New-Object System.Windows.Forms.Label
$TitleLabel.Text = "SchoolLift Biometric Gateway"
$TitleLabel.Font = New-Object System.Drawing.Font("Segoe UI", 17, [System.Drawing.FontStyle]::Bold)
$TitleLabel.Location = New-Object System.Drawing.Point(20, 15)
$TitleLabel.Size = New-Object System.Drawing.Size(500, 35)
$Form.Controls.Add($TitleLabel)

$NoticeLabel = New-Object System.Windows.Forms.Label
$NoticeLabel.Text = "Local setup tool: configure once, then Windows synchronizes automatically in both Shadow and Live modes."
$NoticeLabel.Location = New-Object System.Drawing.Point(23, 50)
$NoticeLabel.Size = New-Object System.Drawing.Size(860, 25)
$Form.Controls.Add($NoticeLabel)

$ConfigGroup = New-Object System.Windows.Forms.GroupBox
$ConfigGroup.Text = "1. Connection settings"
$ConfigGroup.Location = New-Object System.Drawing.Point(20, 82)
$ConfigGroup.Size = New-Object System.Drawing.Size(875, 335)
$ConfigGroup.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Left, Right"
$Form.Controls.Add($ConfigGroup)

$script:SchoolLiftText = Add-InputField $ConfigGroup "SchoolLift school website (HTTPS)" 30
$script:ProviderText = Add-InputField $ConfigGroup "ZKBio API on this computer" 70
$script:SerialText = Add-InputField $ConfigGroup "One terminal serial number" 110
$script:UsernameText = Add-InputField $ConfigGroup "ZKBio read-only API username" 150
$script:PasswordText = Add-InputField $ConfigGroup "ZKBio API password" 190 -Password
$script:TokenText = Add-InputField $ConfigGroup "SchoolLift integration token" 230 -Password

$PhpCaption = New-Object System.Windows.Forms.Label
$PhpCaption.Text = "Detected PHP:"
$PhpCaption.Location = New-Object System.Drawing.Point(18, 270)
$PhpCaption.Size = New-Object System.Drawing.Size(205, 22)
$ConfigGroup.Controls.Add($PhpCaption)
$PhpValue = New-Object System.Windows.Forms.Label
$PhpValue.Text = $(if ($null -eq $script:PhpPath) { "Not found - install/bundle PHP 8.2 before testing" } else { $script:PhpPath })
$PhpValue.Location = New-Object System.Drawing.Point(228, 270)
$PhpValue.Size = New-Object System.Drawing.Size(610, 40)
$PhpValue.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Left, Right"
$ConfigGroup.Controls.Add($PhpValue)

$ButtonTop = 301
$SaveButton = New-Object System.Windows.Forms.Button
$SaveButton.Text = "Save protected configuration"
$SaveButton.Location = New-Object System.Drawing.Point(228, $ButtonTop)
$SaveButton.Size = New-Object System.Drawing.Size(190, 28)
$ConfigGroup.Controls.Add($SaveButton)

$TestButton = New-Object System.Windows.Forms.Button
$TestButton.Text = "Test connections"
$TestButton.Location = New-Object System.Drawing.Point(428, $ButtonTop)
$TestButton.Size = New-Object System.Drawing.Size(150, 28)
$TestButton.Enabled = $null -ne $script:PhpPath
$ConfigGroup.Controls.Add($TestButton)

$InstallButton = New-Object System.Windows.Forms.Button
$InstallButton.Text = "Install / repair automatic sync"
$InstallButton.Location = New-Object System.Drawing.Point(588, $ButtonTop)
$InstallButton.Size = New-Object System.Drawing.Size(250, 28)
$InstallButton.Enabled = $null -ne $script:PhpPath
$ConfigGroup.Controls.Add($InstallButton)

$OperationsGroup = New-Object System.Windows.Forms.GroupBox
$OperationsGroup.Text = "2. Local gateway controls"
$OperationsGroup.Location = New-Object System.Drawing.Point(20, 430)
$OperationsGroup.Size = New-Object System.Drawing.Size(875, 112)
$OperationsGroup.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Left, Right"
$Form.Controls.Add($OperationsGroup)

$StatusButton = New-Object System.Windows.Forms.Button
$StatusButton.Text = "View queue status"
$StatusButton.Location = New-Object System.Drawing.Point(18, 28)
$StatusButton.Size = New-Object System.Drawing.Size(145, 30)
$StatusButton.Enabled = $null -ne $script:PhpPath
$OperationsGroup.Controls.Add($StatusButton)

$SyncButton = New-Object System.Windows.Forms.Button
$SyncButton.Text = "Synchronize now"
$SyncButton.Location = New-Object System.Drawing.Point(173, 28)
$SyncButton.Size = New-Object System.Drawing.Size(145, 30)
$SyncButton.Enabled = $null -ne $script:PhpPath
$OperationsGroup.Controls.Add($SyncButton)

$RetryButton = New-Object System.Windows.Forms.Button
$RetryButton.Text = "Retry failed (advanced)"
$RetryButton.Location = New-Object System.Drawing.Point(328, 28)
$RetryButton.Size = New-Object System.Drawing.Size(175, 30)
$RetryButton.Enabled = $null -ne $script:PhpPath
$OperationsGroup.Controls.Add($RetryButton)

$LogButton = New-Object System.Windows.Forms.Button
$LogButton.Text = "Show recent log"
$LogButton.Location = New-Object System.Drawing.Point(513, 28)
$LogButton.Size = New-Object System.Drawing.Size(145, 30)
$OperationsGroup.Controls.Add($LogButton)

$RemoveButton = New-Object System.Windows.Forms.Button
$RemoveButton.Text = "Remove task"
$RemoveButton.Location = New-Object System.Drawing.Point(668, 28)
$RemoveButton.Size = New-Object System.Drawing.Size(170, 30)
$OperationsGroup.Controls.Add($RemoveButton)

$script:TaskStateLabel = New-Object System.Windows.Forms.Label
$script:TaskStateLabel.Text = "Background task: checking..."
$script:TaskStateLabel.Location = New-Object System.Drawing.Point(18, 70)
$script:TaskStateLabel.Size = New-Object System.Drawing.Size(820, 32)
$script:TaskStateLabel.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Left, Right"
$OperationsGroup.Controls.Add($script:TaskStateLabel)

$ActivityLabel = New-Object System.Windows.Forms.Label
$ActivityLabel.Text = "Activity and diagnostic results"
$ActivityLabel.Location = New-Object System.Drawing.Point(20, 552)
$ActivityLabel.Size = New-Object System.Drawing.Size(300, 22)
$Form.Controls.Add($ActivityLabel)

$script:ActivityText = New-Object System.Windows.Forms.TextBox
$script:ActivityText.Location = New-Object System.Drawing.Point(20, 576)
$script:ActivityText.Size = New-Object System.Drawing.Size(875, 125)
$script:ActivityText.Anchor = [System.Windows.Forms.AnchorStyles]"Top, Bottom, Left, Right"
$script:ActivityText.Multiline = $true
$script:ActivityText.ReadOnly = $true
$script:ActivityText.ScrollBars = "Vertical"
$script:ActivityText.Font = New-Object System.Drawing.Font("Consolas", 9)
$Form.Controls.Add($script:ActivityText)

$FooterLabel = New-Object System.Windows.Forms.Label
$FooterLabel.Text = "Security note: this repository launcher is unsigned. Distribute a code-signed MSI/EXE with bundled PHP for production schools."
$FooterLabel.ForeColor = [System.Drawing.Color]::DarkRed
$FooterLabel.Location = New-Object System.Drawing.Point(20, 712)
$FooterLabel.Size = New-Object System.Drawing.Size(875, 34)
$FooterLabel.Anchor = [System.Windows.Forms.AnchorStyles]"Bottom, Left, Right"
$Form.Controls.Add($FooterLabel)

$SaveButton.Add_Click({
    try {
        Save-ManagerConfiguration | Out-Null
        Refresh-TaskState
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$TestButton.Add_Click({
    try {
        Save-ManagerConfiguration | Out-Null
        $Result = Invoke-FixedGatewayAction "doctor"
        if ($Result.ExitCode -ne 0) {
            throw "Connection test did not pass. Read the diagnostic result below. SchoolLift must be in Shadow or Live mode for the gateway health check."
        }
        [System.Windows.Forms.MessageBox]::Show(
            "ZKBio and SchoolLift connection checks passed.",
            "SchoolLift Gateway Manager",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Information
        ) | Out-Null
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$InstallButton.Add_Click({
    try {
        Save-ManagerConfiguration | Out-Null
        $Doctor = Invoke-FixedGatewayAction "doctor"
        if ($Doctor.ExitCode -ne 0) {
            throw "Automatic synchronization was not installed because the connection test failed. Correct the displayed problem first."
        }
        Protect-GatewayStorage
        Protect-GatewayCode
        & $script:InstallTaskScript `
            -PhpPath $script:PhpPath `
            -ConfigPath $script:ConfigPath `
            -TaskName $script:TaskName `
            -UseLocalService | ForEach-Object { Write-Activity "Task installer" "$_" }
        $InstalledTask = Get-ScheduledTask -TaskName $script:TaskName -ErrorAction Stop
        if (-not (Test-TaskDefinition $InstalledTask)) {
            & $script:UninstallTaskScript -TaskName $script:TaskName | Out-Null
            throw "Windows created a task whose executable, arguments, working directory, or service identity did not match the fixed manager definition. The task was removed."
        }
        Start-ScheduledTask -TaskName $script:TaskName
        Refresh-TaskState
        Write-Activity "Automatic synchronization installed" "Windows will run the same fixed gateway action once per minute in Shadow and Live modes."
        [System.Windows.Forms.MessageBox]::Show(
            "Automatic synchronization is installed. Leave this gateway folder in its current location.",
            "SchoolLift Gateway Manager",
            [System.Windows.Forms.MessageBoxButtons]::OK,
            [System.Windows.Forms.MessageBoxIcon]::Information
        ) | Out-Null
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$StatusButton.Add_Click({
    try {
        Invoke-FixedGatewayAction "status" | Out-Null
        Refresh-TaskState
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$SyncButton.Add_Click({
    try {
        $Answer = [System.Windows.Forms.MessageBox]::Show(
            "Run one synchronization now? Direction and attendance rules still come from SchoolLift; this does not change the operating mode.",
            "Confirm synchronization",
            [System.Windows.Forms.MessageBoxButtons]::YesNo,
            [System.Windows.Forms.MessageBoxIcon]::Question
        )
        if ($Answer -eq [System.Windows.Forms.DialogResult]::Yes) {
            Invoke-FixedGatewayAction "once" | Out-Null
            Refresh-TaskState
        }
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$RetryButton.Add_Click({
    try {
        $Status = Invoke-FixedGatewayAction "status"
        $Parsed = $Status.Text | ConvertFrom-Json
        $Queue = Get-JsonProperty $Parsed "queue"
        $Dead = [int](Get-JsonProperty $Queue "dead" 0)
        if ($Dead -lt 1) {
            [System.Windows.Forms.MessageBox]::Show(
                "There are no permanently failed queue records to retry.",
                "SchoolLift Gateway Manager",
                [System.Windows.Forms.MessageBoxButtons]::OK,
                [System.Windows.Forms.MessageBoxIcon]::Information
            ) | Out-Null
            return
        }
        $Answer = [System.Windows.Forms.MessageBox]::Show(
            "$Dead record(s) are permanently failed. Retry only after correcting the token, mapping, device, or server error. Requeue them now?",
            "Advanced recovery confirmation",
            [System.Windows.Forms.MessageBoxButtons]::YesNo,
            [System.Windows.Forms.MessageBoxIcon]::Warning
        )
        if ($Answer -eq [System.Windows.Forms.DialogResult]::Yes) {
            Invoke-FixedGatewayAction "retry-dead" | Out-Null
        }
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$LogButton.Add_Click({
    try {
        if (-not (Test-Path -LiteralPath $script:LogPath -PathType Leaf)) {
            Write-Activity "Recent gateway log" "No gateway log exists yet."
            return
        }
        $Lines = Get-Content -LiteralPath $script:LogPath -Tail 100 -ErrorAction Stop
        Write-Activity "Recent gateway log (last 100 lines)" ($Lines -join [Environment]::NewLine)
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

$RemoveButton.Add_Click({
    try {
        $Answer = [System.Windows.Forms.MessageBox]::Show(
            "Remove only the automatic Windows task? Protected configuration, queue, and logs will be kept for recovery.",
            "Remove background task",
            [System.Windows.Forms.MessageBoxButtons]::YesNo,
            [System.Windows.Forms.MessageBoxIcon]::Warning
        )
        if ($Answer -eq [System.Windows.Forms.DialogResult]::Yes) {
            & $script:UninstallTaskScript -TaskName $script:TaskName | ForEach-Object { Write-Activity "Task removal" "$_" }
            Refresh-TaskState
        }
    } catch {
        Show-ErrorMessage $_.Exception
    }
})

try {
    $Existing = Read-ManagerConfiguration
    if ($null -ne $Existing) {
        $Provider = Get-JsonProperty $Existing "provider"
        $SchoolLift = Get-JsonProperty $Existing "schoollift"
        $script:GatewayId = [string](Get-JsonProperty $Existing "gateway_id")
        $script:SchoolLiftText.Text = [string](Get-JsonProperty $SchoolLift "base_url")
        $script:ProviderText.Text = [string](Get-JsonProperty $Provider "base_url")
        $script:SerialText.Text = [string](Get-JsonProperty $Provider "terminal_serial")
        $script:UsernameText.Text = [string](Get-JsonProperty $Provider "username")
        $script:PasswordText.Text = [string](Get-JsonProperty $Provider "password")
        $script:TokenText.Text = [string](Get-JsonProperty $SchoolLift "bearer_token")
        Write-Activity "Configuration loaded" "Loaded the protected local JSON configuration. Passwords remain masked on screen."
    } else {
        $script:ProviderText.Text = "http://127.0.0.1:8098"
        Write-Activity "Ready for setup" "Create an integration token on the SchoolLift website, then complete the fields above."
    }
    Refresh-TaskState
} catch {
    Show-ErrorMessage $_.Exception
}

[void]$Form.ShowDialog()
