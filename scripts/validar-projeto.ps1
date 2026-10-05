# Validação rápida do ambiente de desenvolvimento.
$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host ""
Write-Host "=== Folha de Calculo | Validação do projeto ===" -ForegroundColor Cyan
Write-Host ""

$php = "C:\xampp\php\php.exe"
if (-not (Test-Path $php)) {
    throw "PHP do XAMPP não encontrado em $php"
}

$phpFiles = Get-ChildItem -Recurse -Filter *.php | Where-Object {
    $_.FullName -notmatch "\\vendor\\|\\storage\\|\\backup\\"
}

$erros = @()
foreach ($file in $phpFiles) {
    & $php -l $file.FullName | Out-Host
    if ($LASTEXITCODE -ne 0) {
        $erros += $file.FullName
    }
}

$obrigatorios = @(
    "api\config.php",
    "login.php",
    "dashboard.php",
    "historico.php",
    "detalhe.php",
    "index.php",
    "usuarios.php",
    "orcamentos\index.php",
    "orcamentos\processar.php"
)

foreach ($arquivo in $obrigatorios) {
    if (-not (Test-Path $arquivo)) {
        $erros += $arquivo
    }
}

if ($erros.Count -gt 0) {
    Write-Host ""
    Write-Host "Validação falhou nos seguintes itens:" -ForegroundColor Red
    $erros | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host ""
Write-Host "Validação concluída com sucesso." -ForegroundColor Green
Write-Host "PHP: OK"
Write-Host "Arquivos essenciais: OK"
Write-Host ""
