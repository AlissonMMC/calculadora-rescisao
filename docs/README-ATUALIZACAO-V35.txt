V35 — fluxo de nova rescisão e navegação por etapas

1) O menu superior “Nova rescisão” agora abre index.php?nova=1.
   A página entra em estado limpo e mostra primeiro a tela para escolher:
   - Multa por mês
   - Multa por dias

   Não restaura localStorage nem rascunho do servidor nesse fluxo.

2) Os botões de “Etapas do cálculo” agora usam rolagem calculada para a posição
   real do card. O card é aberto antes do scroll e a posição é recalculada em
   dois frames, evitando erro causado pela mudança de altura do conteúdo.
