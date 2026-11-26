# Atualização — Contestação de Vistoria

## O que foi adicionado

- Nova opção **Contestação de vistoria** no menu lateral.
- Cadastro com nome e CPF do inquilino, endereço, data da contestação, contrato e fiança.
- Inclusão dinâmica de cômodos.
- Até 10 fotos por lado (entrada e saída) em cada cômodo; as imagens são redimensionadas no navegador antes do envio.
- Descrição/justificativa individual por cômodo.
- Consulta, edição e exclusão dos registros.
- Relatório de impressão com as fotos de entrada e saída em duas colunas e a descrição abaixo. No navegador, use **Baixar / Salvar em PDF** e escolha **Salvar como PDF** no destino da impressão.
- Controle de acesso: cada usuário visualiza e altera os próprios registros; administradores podem consultar todos.

## Banco de dados

Para banco já existente, selecione primeiro o banco de TESTE `calculadora_rescisao_teste` e execute:

`database/migrations/006_contestacoes_vistoria.sql`

Em instalação nova, as tabelas também estão no `database/schema.sql`.

As tabelas criadas são:
- `contestacoes_vistoria`
- `contestacoes_vistoria_fotos`

As fotografias ficam armazenadas como BLOB no banco e são entregues por endpoint autenticado; não ficam em uma pasta pública.

## Homologação local

1. Faça checkout da branch `feature/contestacao-vistoria`.
2. Atualize o ambiente de teste em `C:\xampp\htdocs\rescisao-teste`.
3. Confirme que o arquivo `.env` do ambiente de teste aponta para `calculadora_rescisao_teste`.
4. Execute a migração acima no banco de teste.
5. Entre no sistema e abra **Contestação de vistoria** no menu lateral.
6. Teste criar, editar, excluir, adicionar/remover cômodos, incluir várias fotos em ambos os lados e gerar o PDF.
7. Verifique também acesso com dois usuários diferentes: um usuário comum não deve visualizar registros de outro usuário.

## Observação sobre o PDF

O relatório é preparado em HTML próprio para impressão A4. O botão abre a janela de impressão do navegador; selecione **Salvar como PDF**. Isso evita adicionar dependências PHP externas ao projeto.
