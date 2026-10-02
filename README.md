# Calculadora de Rescisão de Locação

Sistema web em PHP/MySQL para cálculo, conferência e histórico de rescisões de contratos de locação.

## Funcionalidades

- Login com sessão e controle de acesso
- Perfis de usuário e administrador
- Cálculo de multa por meses ou por dias
- Encargos da rescisão
- Histórico de rescisões
- Rascunho e salvamento automático
- Auditoria de alterações
- Conferência e cobrança
- Módulo de geração de orçamentos
- Processamento de planilhas com Python

## Stack

- PHP 8+
- MySQL/MariaDB
- HTML5
- CSS3
- JavaScript
- Python 3 + openpyxl (módulo de orçamentos)

## Estrutura

```text
/
├── api/                 # Endpoints e configuração do backend
├── assets/              # CSS, JavaScript e recursos visuais
├── database/            # Schema e migrações do banco
├── docs/                # Documentação
├── includes/            # Componentes PHP compartilhados
├── orcamentos/          # Módulo de orçamentos
├── storage/             # Dados gerados em execução (não versionados)
├── tools/               # Scripts operacionais
├── dashboard.php
├── historico.php
├── index.php
├── login.php
└── usuarios.php
```

## Instalação local

1. Instale XAMPP ou outro ambiente PHP + MySQL.
2. Clone o repositório dentro do diretório público do Apache.
3. Crie o banco usando `database/schema.sql`.
4. Ajuste as credenciais do banco no arquivo de configuração conforme seu ambiente.
5. Garanta permissão de escrita em `storage/`.
6. Para o módulo de orçamentos, instale Python e `openpyxl`.
7. Acesse `login.php`.

> Nunca publique senhas reais, dumps de banco, arquivos gerados, PDFs, planilhas ou credenciais no GitHub.

## Próxima etapa: publicação online

A versão atual depende de PHP, MySQL e processamento Python no módulo de orçamentos. Portanto, **GitHub sozinho não hospeda o sistema completo**. O repositório serve como fonte de código; a hospedagem deverá fornecer PHP/MySQL e, para o módulo de orçamentos, execução de Python.

## Segurança

Antes de uma publicação pública, revisar:

- senha inicial do administrador;
- credenciais fora do código-fonte;
- HTTPS;
- permissões de `storage`;
- proteção dos arquivos enviados;
- política de backup;
- limites de upload;
- tratamento de erros sem exposição de detalhes internos.
