param([int]$Port = 8000)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot
$phpPath = 'C:\xampp\php\php.exe'
$mysqlPath = 'C:\xampp\mysql\bin\mysqld.exe'
if (-not (Test-Path -LiteralPath $phpPath)) { throw 'PHP was not found at C:\xampp\php\php.exe. Use php artisan serve with your PHP installation.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor/autoload.php'))) { throw 'Run composer install first. See README.md.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) { throw 'Configure .env first. See README.md.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public/build/manifest.json'))) { throw 'Run pnpm install and pnpm build first.' }

function Test-LocalPort([int]$Number) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $task = $client.ConnectAsync('127.0.0.1', $Number)
        return ($task.Wait(1000) -and $client.Connected)
    } catch { return $false } finally { $client.Dispose() }
}

$databaseConfig = Join-Path $projectRoot 'tmp/mysql/my.ini'
if ((Test-Path -LiteralPath $databaseConfig) -and -not (Test-LocalPort 3307)) {
    Start-Process -FilePath $mysqlPath -ArgumentList ('--defaults-file="' + $databaseConfig + '"'), '--bind-address=127.0.0.1', '--console' -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'tmp/mysql-stdout.log') -RedirectStandardError (Join-Path $projectRoot 'tmp/mysql-stderr.log')
    for ($attempt = 0; $attempt -lt 20; $attempt++) {
        if (Test-LocalPort 3307) { break }
        Start-Sleep -Milliseconds 500
    }
    if (-not (Test-LocalPort 3307)) { throw 'The demo database did not start. Check tmp/mysql-stderr.log.' }
}
$url = 'http://127.0.0.1:' + $Port
if (Test-LocalPort $Port) {
    Write-Host "Port $Port is already in use. If FUL Move is already running, open $url"
    exit 0
}
New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot 'tmp') | Out-Null
$server = Start-Process -FilePath $phpPath -ArgumentList '-S', ('127.0.0.1:' + $Port), '-t', 'public' -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'tmp/server-stdout.log') -RedirectStandardError (Join-Path $projectRoot 'tmp/server-stderr.log') -PassThru
Set-Content -LiteralPath (Join-Path $projectRoot 'tmp/server.pid') -Value $server.Id
Write-Host "FUL Move is starting at $url"
Write-Host 'Demo accounts and presentation steps are in README.md and docs/DEMO.md.'

