# 全新安装：按文件名顺序执行 database/migrations 与 database/seeders 下全部 .sql
# 前提：MySQL 中已存在与 .env 里 DB_NAME 同名的库（建议 utf8mb4）；root 无密码（有密码请在 mysql 命令中加 -p）
# 用法：在 database 目录执行：
#   powershell -ExecutionPolicy Bypass -File .\run_all_migrations_and_seeders.ps1
# DB_NAME 从上一级目录（项目根）的 .env 读取；读不到时默认 uda_v2（与 ECS / run-sql.bat 对齐）

$ErrorActionPreference = "Stop"
$DatabaseDir = $PSScriptRoot
$ProjectRoot = Split-Path -Parent $DatabaseDir
$EnvPath = Join-Path $ProjectRoot ".env"
$MysqlExe = "C:\xampp\mysql\bin\mysql.exe"

function Get-DotEnvValue {
    param([string]$Path, [string]$Key)
    if (-not (Test-Path -LiteralPath $Path)) { return $null }
    foreach ($line in Get-Content -LiteralPath $Path -Encoding UTF8) {
        $t = $line.Trim()
        if ($t.Length -eq 0 -or $t.StartsWith("#")) { continue }
        $idx = $t.IndexOf("=")
        if ($idx -lt 1) { continue }
        $k = $t.Substring(0, $idx).Trim()
        if ($k -ne $Key) { continue }
        $v = $t.Substring($idx + 1).Trim()
        if ($v.Length -ge 2) {
            $fc = $v.Substring(0, 1)
            $lc = $v.Substring($v.Length - 1, 1)
            if (($fc -eq '"' -and $lc -eq '"') -or ($fc -eq "'" -and $lc -eq "'")) {
                $v = $v.Substring(1, $v.Length - 2)
            }
        }
        return $v
    }
    return $null
}

$DbName = Get-DotEnvValue -Path $EnvPath -Key "DB_NAME"
if ([string]::IsNullOrWhiteSpace($DbName)) {
    $DbName = "uda_v2"
    Write-Host "[提示] .env 中未读到 DB_NAME，使用默认: $DbName"
} else {
    Write-Host ("[提示] 使用 .env 中的 DB_NAME: " + $DbName)
}

if (-not (Test-Path $MysqlExe)) {
    Write-Host "[错误] 未找到 MySQL 客户端: $MysqlExe"
    Write-Host "请用记事本打开本脚本，把 `$MysqlExe 改成你本机 mysql.exe 的路径后重试。"
    exit 1
}

$migrationFiles = Get-ChildItem -Path (Join-Path $DatabaseDir "migrations\*.sql") | Sort-Object Name
$seederFiles = Get-ChildItem -Path (Join-Path $DatabaseDir "seeders\*.sql") | Sort-Object Name
$all = @($migrationFiles) + @($seederFiles)

Write-Host ('将对库 [' + $DbName + '] 依次执行 ' + $all.Count + ' 个 SQL 文件...')
foreach ($f in $all) {
    Write-Host ('>>> ' + $f.Name)
    $raw = Get-Content -LiteralPath $f.FullName -Raw -Encoding UTF8
    if ($null -eq $raw -or $raw.Trim().Length -eq 0) {
        Write-Host "    (跳过空文件)"
        continue
    }
    # Windows 下管道喂给 mysql 易导致中文 INSERT 乱码；改为 UTF-8 无 BOM 临时文件 + SOURCE
    $tmp = Join-Path $env:TEMP ('uda_run_' + [Guid]::NewGuid().ToString('N') + '.sql')
    $utf8NoBom = New-Object System.Text.UTF8Encoding $false
    $fullSql = 'SET NAMES utf8mb4;' + [Environment]::NewLine + $raw
    try {
        [System.IO.File]::WriteAllText($tmp, $fullSql, $utf8NoBom)
        $src = ($tmp -replace '\\', '/')
        & $MysqlExe -u root --default-character-set=utf8mb4 $DbName -e ('source ' + $src)
        if ($LASTEXITCODE -ne 0) {
            Write-Host ('[失败] 执行 ' + $f.Name + ' 时 mysql 返回非零。请根据上方英文报错排查。')
            exit $LASTEXITCODE
        }
    } finally {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    }
}

Write-Host ""
Write-Host ('[完成] 全部 SQL 已执行。请到 DBeaver 刷新数据库「' + $DbName + '」下的表，并用 README 启动网站试登录。')
