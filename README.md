# Calculadora de Rescisão de Locação

Sistema web para **cálculo, conferência e gerenciamento de rescisões de contratos de locação**.

O projeto foi desenvolvido com foco em uso profissional, organização de processos e redução de tarefas manuais no setor imobiliário.

> **Status:** em desenvolvimento ativo  
> **Versão:** v1.x  
> **Ambiente principal:** PHP + MySQL/MariaDB + JavaScript

---

## ✨ Principais recursos

- 🔐 Autenticação e controle de acesso por e-mail
- 👥 Gestão de usuários e permissões
- ✉️ Convite de primeiro acesso por e-mail e definição de senha pelo usuário
- 🧮 Cálculo de rescisão por **meses ou dias**
- 💰 Cálculo de aluguel e encargos
- 🧾 Cálculo de multa rescisória
- 📝 Rascunhos e recuperação de cálculos
- 📚 Histórico de rescisões
- 🔎 Conferência antes da cobrança
- 🕵️ Registro de alterações e auditoria
- 📊 Módulo de geração e processamento de orçamentos
- 📑 Integração com planilhas
- 🐍 Processamento auxiliar com Python

---

## 🛠️ Tecnologias

| Tecnologia | Utilização |
|---|---|
| **PHP 8+** | Backend e regras de negócio |
| **MySQL / MariaDB** | Persistência dos dados |
| **HTML5** | Estrutura das interfaces |
| **CSS3** | Interface e responsividade |
| **JavaScript** | Interações e cálculos no frontend |
| **Python 3** | Processamento do módulo de orçamentos |
| **openpyxl** | Manipulação de arquivos Excel |
| **Apache / XAMPP** | Ambiente de desenvolvimento local |

---

## 📁 Estrutura do projeto

```text
calculadora-rescisao/
├── api/                    # Endpoints e backend
├── assets/                 # CSS e JavaScript
├── database/               # Schema e migrações
├── docs/                   # Documentação técnica
├── includes/               # Componentes PHP compartilhados
├── orcamentos/             # Módulo de orçamentos
├── storage/                # Arquivos gerados em execução
├── tools/                  # Scripts auxiliares
├── .env.example            # Modelo de configuração
├── .gitignore
├── .htaccess
├── dashboard.php
├── historico.php
├── index.php
├── login.php
└── usuarios.php
```

---

## 🚀 Instalação local

### 1. Pré-requisitos

- PHP 8+
- Apache
- MySQL ou MariaDB
- Python 3
- pip
- openpyxl
- Composer 2+
- XAMPP ou ambiente equivalente

### 2. Clonar o projeto

```bash
git clone https://github.com/AlissonMMC/calculadora-rescisao.git
cd calculadora-rescisao
```

### 3. Configurar o banco

Crie um banco de dados e execute:

```text
database/schema.sql
```

As migrações adicionais estão em:

```text
database/migrations/
```

### 4. Configurar o ambiente

Use .env.example como referência para as variáveis do ambiente.

**Nunca coloque credenciais reais no Git.**

### 5. Instalar dependências PHP e do módulo de orçamentos

```bash
composer install
python -m pip install openpyxl
```

### 6. Configurar envio de e-mail

Preencha no `.env` as variáveis `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` e `APP_URL`.

O cadastro de usuário usa o e-mail como nome de usuário e não define uma senha. O sistema gera um convite individual, envia o link por SMTP e permite que o próprio usuário crie a senha na tela de primeiro acesso. O convite é de uso único e possui expiração configurável por `INVITE_EXPIRATION_HOURS`.

### 7. Executar

Com Apache e MySQL ativos no XAMPP, acesse o projeto pelo navegador através do endereço configurado no Apache.

---

## 🔒 Segurança

Antes de colocar o sistema em produção, revise principalmente:

- credenciais do banco de dados;
- usuários e senhas iniciais;
- armazenamento de sessões;
- HTTPS;
- permissões de escrita;
- arquivos enviados pelos usuários;
- limites de upload;
- execução de scripts Python;
- mensagens de erro em produção;
- backups e recuperação de dados.

Arquivos com dados reais de clientes, contratos, planilhas, PDFs, senhas ou credenciais **não devem ser publicados neste repositório**.

Consulte também SECURITY.md.

---

## 🧪 Desenvolvimento

O projeto possui uma estrutura separada por responsabilidades:

- api/ → operações do backend;
- assets/ → frontend;
- database/ → estrutura do banco;
- orcamentos/ → processamento de orçamentos;
- docs/ → documentação;
- tools/ → automações e manutenção.

Ao realizar alterações, procure manter essa separação e evitar código duplicado.

---

## 📌 Roadmap

- [x] Estrutura inicial do sistema
- [x] Autenticação e permissões
- [x] Histórico de rescisões
- [x] Rascunhos
- [x] Auditoria
- [x] Módulo de orçamentos
- [x] Publicação do código no GitHub
- [x] Convite de primeiro acesso por e-mail
- [ ] Configuração de ambiente de produção
- [ ] Pipeline de testes e validação automática
- [ ] Melhorias de segurança para produção
- [ ] Documentação técnica completa
- [ ] Publicação online
- [ ] Monitoramento e backup automatizado

---

## 🤝 Contribuição

O projeto está em desenvolvimento ativo. Para alterações maiores, recomenda-se abrir uma issue antes de implementar a mudança.

Consulte CONTRIBUTING.md.

---

## 📄 Licença

Este projeto está distribuído sob a licença MIT. Consulte LICENSE.

---

## 👨‍💻 Autor

**Alisson MMC**

Projeto desenvolvido como solução prática para automação e organização de processos de rescisão no setor imobiliário.

[GitHub](https://github.com/AlissonMMC)
