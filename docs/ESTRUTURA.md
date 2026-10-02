# Estrutura técnica

## Camadas

### Interface
`assets/css/` e `assets/js/` concentram apresentação e comportamento do navegador.

### Aplicação
Os arquivos PHP na raiz funcionam como páginas/entradas HTTP. APIs ficam em `api/`.

### Módulo de orçamentos
O módulo está isolado em `orcamentos/`, com seus helpers em `orcamentos/includes/` e ferramentas Python em `orcamentos/python/`.

### Persistência
Banco: `database/`. Arquivos persistentes: `storage/`.

### Operação
Scripts de backup: `tools/backup/`.

### Legado
Arquivos antigos e implementações substituídas: `legacy/`. Eles não fazem parte do fluxo ativo.
