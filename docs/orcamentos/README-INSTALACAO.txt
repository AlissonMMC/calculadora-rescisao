GERADOR DE ORÇAMENTOS — INSTALAÇÃO

1) Copie a pasta orcamentos para:
   C:\xampp\htdocs\rescisao\orcamentos

2) O gerador usa:
   - Python + openpyxl
   - LibreOffice para recálculo/exportação PDF
   - PHP/Apache do XAMPP

3) IMPORTANTE — DADOS PERSISTENTES
   As assinaturas, prestadores, configurações, histórico, logs e arquivos de processamento ficam em:
   C:\xampp\htdocs\rescisao\storage\orcamentos

   Essa pasta fica FORA da pasta orcamentos. Portanto, ao atualizar o sistema substituindo a pasta orcamentos, as assinaturas NÃO são apagadas.

4) Para cadastrar uma assinatura:
   Orçamentos → Prestadores → selecione/edite o prestador → envie a assinatura PNG/JPG → Salvar prestador.

   Depois disso, a assinatura aparece automaticamente no gerador sempre que aquele prestador for selecionado.

5) A migração é automática: se ainda existirem dados em orcamentos\storage de uma versão anterior, o sistema copia esses dados para storage\orcamentos na primeira abertura.
