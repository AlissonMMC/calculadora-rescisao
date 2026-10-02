V31 — REFINAMENTO UX + CORREÇÃO ROBUSTA DA EDIÇÃO PELO HISTÓRICO

1. A calculadora recebeu refinamento visual profissional, com hierarquia mais clara,
   espaçamento reduzido, barra de etapas mais compacta e resumo financeiro fixo em desktop.
2. Os cards 4–7 continuam em duas colunas independentes, pequenos e sem textos auxiliares
   ocupando espaço desnecessário.
3. O painel de resumo acompanha a rolagem em desktop para reduzir deslocamentos do usuário.
4. A edição de rescisão agora usa o endpoint dedicado carregar_edicao.php, que lê o dados_json
   bruto diretamente do banco e faz decodificação compatível com versões antigas.
5. A edição prioriza os dados retornados do banco e usa as APIs anteriores apenas como fallback.
6. Foi reforçado o tratamento de checkboxes (booleanos e strings) e de JSON duplamente codificado.
7. Ao abrir um histórico, o rascunho do usuário não é carregado nem sobrescreve os dados do histórico.
8. O registro central continua sendo alterado somente ao salvar explicitamente.
