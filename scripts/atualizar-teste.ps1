# Atualiza o ambiente de teste local a partir da branch develop.
# Uso:
#   powershell -ExecutionPolicy Bypass -File .\scripts\atualizar-teste.ps1

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host ""
Write-Host "=== Folha de Calculo | Atualizacao do ambiente de TESTE ===" -ForegroundColor Cyan
Write-Host ""

$status = git status --porcelain
if ($status) {
    Write-Host "Existem alteracoes locais. Elas nao serao sobrescritas." -ForegroundColor Yellow
    Write-Host ""
    git status --short
    Write-Host ""
    Write-Host "Antes de atualizar, faca commit ou stash das alteracoes locais." -ForegroundColor Yellow
    exit 1
}

git fetch origin

$currentBranch = git branch --show-current
if ($currentBranch -ne "develop") {
    git checkout develop
}

git pull --ff-only origin develop

Write-Host ""
Write-Host "Atualizacao concluida com sucesso." -ForegroundColor Green
Write-Host "Branch: develop"
Write-Host "URL: http://localhost/calculadora-rescisao/"
Write-Host ""
git status
