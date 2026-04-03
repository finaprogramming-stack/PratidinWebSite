param(
    [Parameter(Mandatory = $true)]
    [string]$Path,

    [ValidateSet('Edge', 'Chrome')]
    [string]$Browser = 'Edge'
)

$resolvedPath = (Resolve-Path -LiteralPath $Path).Path
$uri = [System.Uri]::new($resolvedPath).AbsoluteUri

$browserPath = switch ($Browser) {
    'Chrome' { 'C:\Program Files\Google\Chrome\Application\chrome.exe' }
    'Edge' { 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' }
}

if (-not (Test-Path -LiteralPath $browserPath)) {
    throw "Browser executable not found: $browserPath"
}

Start-Process -FilePath $browserPath -ArgumentList @('--new-window', $uri)
