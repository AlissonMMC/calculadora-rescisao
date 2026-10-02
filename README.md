# Calculadora de Rescisão de Locação

Sistema web em PHP/MySQL para cálculo, conferência e histórico de rescisões de contratos de locação.

## Funcionalidades
- Login e controle de acesso
- Perfis de usuário e administrador
- Cálculo de multa por meses ou dias
- Encargos da rescisão
- Histórico e rascunhos
- Auditoria de alterações
- Conferência e cobrança
- Módulo de geração de orçamentos
- Processamento de planilhas com Python

## Stack
- PHP 8+
- MySQL/MariaDB
- HTML5, CSS3 e JavaScript
- Python 3 + openpyxl para o módulo de orçamentos

## Desenvolvimento local
1. Instale XAMPP ou outro ambiente PHP + MySQL.
2. Clone o repositório para o diretório público do Apache.
3. Crie o banco usando `database/schema.sql`.
4. Configure as credenciais em `api/config.php` ou a configuração equivalente do ambiente.
5. Para o módulo de orçamentos, instale Python e openpyxl.
6. Acesse `login.php`.

> Nunca publique senhas reais, dumps de banco, arquivos gerados ou credenciais no GitHub.

## Publicação
O GitHub funciona como repositório do código. A hospedagem online deverá fornecer PHP, MySQL/MariaDB e, para o módulo de orçamentos, execução de Python.
