OPÇÃO B — GERADOR DE ORÇAMENTOS WEB (BETA)

Esta versão é um teste independente do módulo de orçamentos. Ela NÃO usa VBA nem abre o Excel para executar macros.

INSTALAÇÃO NO SEU XAMPP
1. Copie esta pasta "orcamentos" para:
   C:\xampp\htdocs\rescisao\orcamentos

2. No servidor, instale:
   - Python 3
   - openpyxl (pip install openpyxl)
   - Pillow (pip install pillow)
   - LibreOffice (necessário para PDF)

3. Abra:
   http://localhost/rescisao/orcamentos/verificar.php
   para verificar o ambiente.

4. Abra:
   http://localhost/rescisao/orcamentos/

5. O módulo reaproveita o login existente do sistema.

REGRAS IMPLEMENTADAS A PARTIR DA AUTOMAÇÃO ATUAL
- Seleciona o arquivo XLSX e a planilha.
- Não altera o arquivo original.
- Cria "Planilha de Acionamento.xlsx".
- Cria as abas "Orçamento", "Orçamento 2" e "Orçamento 3".
- +10 e +20 nos valores positivos de F/G; zeros permanecem zero; vazios permanecem vazios.
- A regra de múltiplos de 10 segue a automação atual.
- Atualiza A2/A3 com o prestador correspondente.
- Atualiza A4, D4:I8, F11, G11 e I11.
- Oculta D/E/H e o bloco a partir de PINTURA INTERNA.
- Insere imagens dos três prestadores.
- Tenta gerar os três PDFs em arquivos separados.

LIMITAÇÃO IMPORTANTE DO TESTE
A automação web não reproduz todas as particularidades internas do Excel com 100% de fidelidade sem validar o arquivo XLSX real, principalmente fórmulas complexas, objetos gráficos, imagens embutidas, nomes definidos e recursos específicos do Excel.

Para validar a equivalência com o seu processo atual, use um XLSX real de orçamento e compare o Excel e os 3 PDFs gerados com os arquivos produzidos pelo VBA.
