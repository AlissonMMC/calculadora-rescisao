<?php
require __DIR__ . '/api/config.php';
$usuario = exigirLoginPagina();
$navPage = 'rescisao';
$navBase = "";
$csrf = csrfToken();

// Quando a tela é aberta para edição pelo histórico, carregamos o registro
// diretamente do banco antes de renderizar a calculadora. Assim a edição não
// depende de uma segunda requisição JavaScript nem corre o risco de iniciar
// com os valores padrão zerados.
$historicoInicialId = max(0, (int)($_GET['historico_id'] ?? 0));
$historicoInicial = null;
if ($historicoInicialId > 0) {
  try {
    $stmtHistoricoInicial = db()->prepare('SELECT h.id, h.usuario_id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse, h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json, u.nome AS usuario_nome, u.login AS usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.id = ? LIMIT 1');
    $stmtHistoricoInicial->execute([$historicoInicialId]);
    $rowHistoricoInicial = $stmtHistoricoInicial->fetch();
    if ($rowHistoricoInicial) {
      // Mantemos o JSON bruto salvo no banco. A edição usa um endpoint
      // dedicado para decodificar esse conteúdo sem depender de normalizadores
      // usados somente na listagem. Isso evita que a tela de edição receba
      // valores padrão/zerados por uma transformação intermediária.
      $historicoInicial = [
        'id' => (int)$rowHistoricoInicial['id'],
        'usuario_id' => (int)$rowHistoricoInicial['usuario_id'],
        'nome' => (string)($rowHistoricoInicial['nome'] ?? ''),
        'endereco' => (string)($rowHistoricoInicial['endereco'] ?? ''),
        'total' => (float)($rowHistoricoInicial['total'] ?? 0),
        'total_adm' => (float)($rowHistoricoInicial['total_adm'] ?? 0),
        'total_repasse' => (float)($rowHistoricoInicial['total_repasse'] ?? 0),
        'criado_em' => $rowHistoricoInicial['criado_em'],
        'atualizado_em' => $rowHistoricoInicial['atualizado_em'],
        'modo_nome' => (string)($rowHistoricoInicial['modo_nome'] ?? ''),
        'status' => (string)($rowHistoricoInicial['status'] ?? 'Rascunho'),
        'dados_json' => (string)($rowHistoricoInicial['dados_json'] ?? ''),
        'usuario_nome' => (string)($rowHistoricoInicial['usuario_nome'] ?? ''),
        'usuario_login' => (string)($rowHistoricoInicial['usuario_login'] ?? ''),
      ];
    }
  } catch (Throwable $e) {
    error_log('Calculadora preload histórico: ' . $e->getMessage());
  }
}
?>
<?php
$iniciais = strtoupper(mb_substr(trim($usuario['nome']), 0, 1));
$perfilLabel = ($usuario['perfil'] === 'admin') ? 'Administrador' : 'Usuário';
$adminLink = ($usuario['perfil'] === 'admin') ? '<a class="session-button session-button-link" href="usuarios.php">Usuários</a>' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Calculadora de Rescisão de Locação</title>
  <meta name="description" content="Calculadora para rescisão de contratos de locação com multa por mês ou por dias." />
  <link rel="stylesheet" href="assets/css/pages/calculadora.css">
</head>
<body>
  <?php require __DIR__ . "/includes/nav_global.php"; ?>
  <main class="container">

<header class="header home-hero">
      <div class="hero-glow hero-glow-one"></div>
      <div class="hero-glow hero-glow-two"></div>
      <div class="header-content">
        <span class="eyebrow"><span class="eyebrow-dot"></span> Sistema de gestão de rescisões</span>
        <h1>Rescisão de contrato<br><span>de locação.</span></h1>
        <p>Calcule valores, confira os dados e prepare a cobrança de forma rápida, organizada e segura.</p>
        <div class="hero-points">
          <span>✓ Cálculo automático</span>
          <span>✓ Histórico salvo</span>
          <span>✓ Conferência final</span>
        </div>
      </div>
      <div class="hero-symbol" aria-hidden="true">
        <div class="hero-symbol-card">
          <span class="hero-symbol-label">RESCISÃO</span>
          <strong>R$</strong>
          <span class="hero-symbol-line"></span>
          <small>Locação imobiliária</small>
        </div>
      </div>
    </header>

    <section id="modeScreen" class="mode-screen home-mode">
      <div class="home-mode-head">
        <div>
          <span class="section-kicker">PASSO 01</span>
          <h2>Escolha o critério da multa</h2>
          <p>Selecione a regra prevista no contrato para iniciar o cálculo.</p>
        </div>
        <div class="home-secure">🔒 <span>Histórico centralizado<br>no servidor</span></div>
      </div>

      <div class="mode-grid">
        <button class="mode-option" type="button" data-mode="mes">
          <div class="mode-option-top">
            <div class="mode-icon mode-icon-month"><span>36</span></div>
            <span class="mode-arrow">↗</span>
          </div>
          <div class="mode-label">CRITÉRIO 01</div>
          <h3>Multa por mês</h3>
          <p>Utilize quando o contrato define a multa com base nos meses que faltam para completar o período contratual.</p>
          <div class="mode-formula"><span>Fórmula</span> Aluguel × 3 ÷ 36 × meses faltantes</div>
        </button>

        <button class="mode-option" type="button" data-mode="dias">
          <div class="mode-option-top">
            <div class="mode-icon mode-icon-days"><span>DIAS</span></div>
            <span class="mode-arrow">↗</span>
          </div>
          <div class="mode-label">CRITÉRIO 02</div>
          <h3>Multa por dias</h3>
          <p>Utilize quando o contrato considera os dias faltantes entre a data de referência e o encerramento.</p>
          <div class="mode-formula"><span>Fórmula</span> Aluguel × 3 ÷ 1095 × dias faltantes</div>
        </button>
      </div>

      <div class="selected-mode hidden" id="selectedMode">
        <span><small>Critério selecionado</small><strong id="selectedModeText"></strong></span>
        <button class="btn-light btn-continue-home" id="btnContinuar" type="button">Continuar →</button>
      </div>

      <div class="home-features">
        <div><strong>01</strong><span>Informe os dados<br>do contrato</span></div>
        <div><strong>02</strong><span>Confira os<br>valores calculados</span></div>
        <div><strong>03</strong><span>Finalize e<br>gere a cobrança</span></div>
      </div>
    </section>

    <section id="calculatorScreen" class="hidden">
      <div class="print-meta" id="printMeta">Cálculo de rescisão de locação — gerado em <span id="printDate"></span></div>
      <div class="print-only" id="printOnlyHeader">RESCISÃO DE CONTRATO DE LOCAÇÃO</div>
      <div class="selected-mode" style="margin-bottom:12px;">
        <span><span class="info-chip">Critério</span> <strong id="currentModeText"></strong> <span class="info-chip lock-badge">🔒 Revisão bloqueada</span></span>
        <div class="buttons" style="margin-top:0;">
          <button class="btn-light" id="btnBloquear" type="button">🔒 Bloquear revisão</button>
          <button class="btn-light" id="btnAlterarModo" type="button">Alterar tipo de multa</button>
        </div>
      </div>
      <div class="save-status" id="saveStatus"><span class="save-dot"></span><span id="saveStatusText">Salvamento automático ativo</span></div>
      <div class="alert-box" id="alertBox"></div>

      <div class="calculator-toolbar" aria-label="Fluxo compacto da calculadora">
        <div class="calculator-toolbar-top">
          <div class="calculator-toolbar-title">
            <div class="toolbar-icon">✓</div>
            <div><strong>Etapas do cálculo</strong><span>Abra somente o que precisar. Os valores continuam sendo calculados automaticamente.</span></div>
          </div>
          <div class="calculator-toolbar-actions">
            <button class="toolbar-btn" id="btnAbrirTopicos" type="button">Abrir todos</button>
            <button class="toolbar-btn" id="btnFecharTopicos" type="button">Fechar todos</button>
          </div>
        </div>
        <div class="workflow-steps" id="workflowSteps">
          <button class="workflow-step" type="button" data-topic="topicoDiasFinais"><span class="step-num">1</span>Dias finais</button>
          <button class="workflow-step" type="button" data-topic="topicoAviso"><span class="step-num">2</span>Aviso</button>
          <button class="workflow-step" type="button" data-topic="topicoMulta"><span class="step-num">3</span>Multa</button>
          <button class="workflow-step" type="button" data-topic="topicoAluguelInteiro"><span class="step-num">4</span>Aluguel</button>
          <button class="workflow-step" type="button" data-topic="topicoManutencao"><span class="step-num">5</span>Manutenção</button>
          <button class="workflow-step" type="button" data-topic="topicoChaveiro"><span class="step-num">6</span>Chaveiro</button>
          <button class="workflow-step" type="button" data-topic="topicoSeguros"><span class="step-num">7</span>Seguros</button>
        </div>
      </div>

      <div class="layout">
        <section>
          <div class="card" id="topicoDiasFinais">
            <div class="section-heading">
              <div class="section-number">1</div>
              <div>
                <h2 class="section-title">Valores dos dias finais</h2>
                <p class="section-subtitle">Informe a data inicial e a data de entrega das chaves. A contagem considera as duas datas.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaDias">?</button><button class="btn-mini" type="button" data-clear-topic="dias">Limpar tópico</button></div>
              </div>
            </div>

            <div class="date-range">
              <div class="field">
                <label for="dataInicial">Data inicial</label>
                <input id="dataInicial" type="date" />
                <span class="field-error" id="erroDataInicial">Informe uma data válida.</span>
              </div>
              <div class="date-arrow" aria-hidden="true">→</div>
              <div class="field">
                <label for="dataFinal">Entrega das chaves</label>
                <input id="dataFinal" type="date" />
                <span class="field-error" id="erroDataFinal">A entrega deve ser igual ou posterior à data inicial.</span>
              </div>
            </div>

            <div class="days-result" aria-live="polite">
              <div>
                <div class="label">Dias corridos no período</div>
                <span class="hint" id="periodoTexto">Informe as duas datas</span>
              </div>
              <strong id="diasCalculados">0 dias</strong>
            </div>

            <div class="grid" style="margin-top:16px;">
              <div class="field full">
                <label class="check-inline"><input id="semEncargos" type="checkbox" /> Não há encargos neste contrato</label>
                <span class="hint">Marque apenas se não houver IPTU, condomínio, água, luz ou internet a cobrar.</span>
              </div>
              <div class="field">
                <label for="aluguel">Valor do aluguel (R$)</label>
                <input id="aluguel" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
              </div>
              <div class="field optional-debt-field">
                <label for="iptu">IPTU mensal (R$)</label>
                <input id="iptu" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                
              </div>
              <div class="field optional-debt-field">
                <label for="condominio">Condomínio mensal (R$)</label>
                <input id="condominio" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                
              </div>
              <div class="field optional-debt-field">
                <label for="agua">Água mensal (R$)</label>
                <input id="agua" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                
              </div>
              <div class="field optional-debt-field">
                <label for="luz">Luz mensal (R$)</label>
                <input id="luz" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                
              </div>
              <div class="field optional-debt-field">
                <label for="internet">Internet mensal (R$)</label>
                <input id="internet" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                
              </div>
              <div class="field">
                <label for="percentualAdm">ADM do aluguel (%)</label>
                <input id="percentualAdm" type="number" min="0" max="100" step="0.01" value="0" inputmode="decimal" autocomplete="off" />
                <span class="hint">Informe uma única vez. A mesma ADM será aplicada ao aluguel inteiro, dias finais e aviso prévio.</span>
              </div>
            </div>

            <div class="formula">
              <strong>Fórmula de cada item:</strong> valor mensal ÷ 30 × dias corridos
              <br />
              <span id="formulaDias">Informe um período válido para visualizar o cálculo proporcional.</span>
              <br />
              <strong>ADM do aluguel nos dias finais:</strong> <span id="formulaAdmDiasFinais">R$ 0,00 × 0% = R$ 0,00</span>
              <br />
              <strong>Repasse dos dias finais ao proprietário:</strong> <span id="formulaRepasseDiasFinais">R$ 0,00 − R$ 0,00 = R$ 0,00</span>
              <br />
              <strong>Exemplo de datas:</strong> 01/10/2026 até 10/10/2026 = 10 dias.
            </div>
            <div class="explain-box" id="explicaDias">Dias finais: cada valor mensal é dividido por 30 e multiplicado pela quantidade de dias do período. A ADM é aplicada somente sobre o aluguel proporcional.</div>

            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Item</th><th>Valor proporcional</th></tr></thead>
                <tbody id="proporcionaisBody"></tbody>
                <tfoot><tr class="row-total"><td>Total dos valores proporcionais</td><td id="totalProporcionais">R$ 0,00</td></tr></tfoot>
              </table>
            </div>
          </div>

          <div class="card" id="topicoAviso">
            <div class="section-heading">
              <div class="section-number">2</div>
              <div>
                <h2 class="section-title">Aviso prévio</h2>
                <p class="section-subtitle">Informe as datas do aviso prévio. O aviso considera somente o aluguel proporcional do período informado.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaAviso">?</button><button class="btn-mini" type="button" data-clear-topic="aviso">Limpar tópico</button></div>
                <label class="check-inline"><input id="semAviso" type="checkbox" /> Não há aviso prévio neste contrato</label>
                <div class="explain-box" id="explicaAviso">Aviso prévio: a contagem dos dias usa as duas datas informadas, mas o valor cobrado considera somente o aluguel proporcional. IPTU, condomínio, água, luz e internet não entram neste cálculo. A ADM incide somente sobre o aluguel.</div>
              </div>
            </div>

            <div class="date-range">
              <div class="field">
                <label for="dataInicioAviso">Início do aviso prévio</label>
                <input id="dataInicioAviso" type="date" />
                <span class="field-error" id="erroInicioAviso">Informe uma data válida.</span>
              </div>
              <div class="date-arrow" aria-hidden="true">→</div>
              <div class="field">
                <label for="dataFimAviso">Fim do aviso prévio</label>
                <input id="dataFimAviso" type="date" />
                <span class="field-error" id="erroFimAviso">O fim deve ser igual ou posterior ao início.</span>
              </div>
            </div>

            <div class="days-result" aria-live="polite">
              <div>
                <div class="label">Dias do aviso prévio</div>
                <span class="hint" id="periodoAvisoTexto">Informe as duas datas</span>
              </div>
              <strong id="diasAviso">0 dias</strong>
            </div>

            <div class="formula">
              <strong>Fórmula do aviso:</strong> aluguel mensal ÷ 30 × dias do aviso prévio
              <br />
              <span id="formulaAviso">Informe um período válido para visualizar o cálculo proporcional.</span>
              <br />
              <strong>ADM do aluguel no aviso:</strong> <span id="formulaAdmAviso">R$ 0,00 × 0% = R$ 0,00</span>
              <br />
              <strong>Repasse do aviso ao proprietário:</strong> <span id="formulaRepasseAviso">R$ 0,00 − R$ 0,00 = R$ 0,00</span>
            </div>

            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Item</th><th>Valor proporcional</th></tr></thead>
                <tbody id="avisoBody"></tbody>
                <tfoot><tr class="row-total"><td>Total do aviso prévio</td><td id="totalAviso">R$ 0,00</td></tr></tfoot>
              </table>
            </div>
          </div>

          <div class="card" id="topicoMulta">
            <div class="section-heading">
              <div class="section-number">3</div>
              <div>
                <h2 class="section-title">Multa rescisória</h2>
                <p class="section-subtitle" id="multaSubtitle">Informe os dados necessários para calcular a multa.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaMulta">?</button><button class="btn-mini" type="button" data-clear-topic="multa">Limpar tópico</button></div>
                <label class="check-inline"><input id="semMulta" type="checkbox" /> Não há multa rescisória neste contrato</label>
                <div class="explain-box" id="explicaMulta">A multa segue o critério selecionado: por mês (aluguel × 3 ÷ 36 × meses faltantes) ou por dias (aluguel × 3 ÷ 1095 × dias faltantes).</div>
              </div>
            </div>

            <div id="multaMesFields" class="grid">
              <div class="field">
                <label for="mesesFaltantes">Meses faltantes para 36 meses</label>
                <input id="mesesFaltantes" type="number" min="0" max="36" step="1" value="0" inputmode="numeric" />
              </div>
              <div class="field">
                <label>Critério</label>
                <div class="check-field"><span class="hint">Multa calculada pelo número de meses faltantes.</span></div>
              </div>
            </div>

            <div id="multaDiasFields" class="hidden">
              <div class="grid" style="margin-top:0; margin-bottom:14px;">
                <div class="field">
                  <label for="dataInicioContrato">Início do contrato</label>
                  <input id="dataInicioContrato" type="date" />
                  <span class="field-error" id="erroInicioContrato">O início do contrato não pode ser igual à data inicial dos dias finais.</span>
                  <span class="hint">Esta data é usada somente para calcular os dias do inquilino no imóvel para a multa por dias.</span>
                </div>
                <div class="field">
                  <label>Entrega das chaves</label>
                  <div class="check-field"><span class="hint">Utiliza a mesma data de entrega informada nos dias finais.</span></div>
                </div>
              </div>
              <div class="days-result" style="margin-top:0;">
                <div>
                  <div class="label">Dias do inquilino no imóvel</div>
                  <span class="hint">Contagem do início do contrato até a entrega das chaves, incluindo as duas datas.</span>
                </div>
                <strong id="diasDoInquilino">0 dias</strong>
              </div>
              <div class="days-result" style="margin-top:10px;">
                <div>
                  <div class="label">Dias faltantes</div>
                  <span class="hint">1095 − dias do inquilino no imóvel</span>
                </div>
                <strong id="diasFaltantes">0 dias</strong>
              </div>
            </div>

            <div class="grid" style="margin-top:14px;">
              <div class="field">
                <label for="percentualProp">ADM sobre a multa (%)</label>
                <input id="percentualProp" type="number" min="0" max="100" step="0.01" value="0" inputmode="decimal" />
                <span class="hint">Percentual da multa que fica com a imobiliária.</span>
              </div>
            </div>

            <div class="formula">
              <strong>Fórmula da multa:</strong> <span id="formulaMulta">R$ 0,00 × 3 ÷ 36 × 0 = R$ 0,00</span>
              <br />
              <strong>ADM da imobiliária sobre a multa:</strong> <span id="formulaAdmMulta">R$ 0,00 × 0% = R$ 0,00</span>
              <br />
              <strong>Repasse da multa ao proprietário:</strong> <span id="formulaRepasseMulta">R$ 0,00 − R$ 0,00 = R$ 0,00</span>
            </div>
          </div>

          <div class="optional-topics-columns" aria-label="Cobranças adicionais">
            <div class="optional-topics-column">
<div class="card compact-topic-card" id="topicoAluguelInteiro">
            <div class="section-heading">
              <div class="section-number">4</div>
              <div>
                <h2 class="section-title">Aluguel inteiro</h2>
                <p class="section-subtitle">Use quando houver um aluguel mensal completo a ser cobrado na rescisão.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaAluguel">?</button><button class="btn-mini" type="button" data-clear-topic="aluguel">Limpar tópico</button></div>
                <div class="explain-box" id="explicaAluguel">O aluguel inteiro soma aluguel, encargos e os seguros marcados para cobrança nesta seção. A ADM incide somente sobre o aluguel. Seguros não fazem parte do repasse ao proprietário.</div>
              </div>
            </div>

            <div class="grid">
              <div class="field full optional-debt-field">
                <label for="valorAluguelInteiro">Aluguel inteiro + encargos (R$)</label>
                <input id="valorAluguelInteiro" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" readonly disabled />
                <label class="check-inline"><input id="semAluguelInteiro" type="checkbox" /> Não há aluguel inteiro nesta rescisão</label>
                <span class="hint">O valor é calculado automaticamente quando houver aluguel inteiro: aluguel + IPTU + condomínio + água + luz + internet + seguro incêndio + seguro fiança.</span>
              </div>
            </div>

            <div class="formula">
              <strong>ADM do aluguel (somente sobre o aluguel):</strong> <span id="formulaAdmAluguel">R$ 0,00 × 0% = R$ 0,00</span>
              <br />
              <strong>Repasse do aluguel ao proprietário:</strong> <span id="formulaRepasseAluguel">R$ 0,00 − R$ 0,00 = R$ 0,00</span>
            </div>
          </div>
<div class="card compact-topic-card" id="topicoChaveiro">
            <div class="section-heading">
              <div class="section-number">6</div>
              <div>
                <h2 class="section-title">Chaveiro</h2>
                <p class="section-subtitle">Informe o valor de chaveiro, quando houver, para acrescentar à cobrança da rescisão.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaChaveiro">?</button><button class="btn-mini" type="button" data-clear-topic="chaveiro">Limpar tópico</button></div>
              </div>
            </div>

            <div class="grid">
              <div class="field full optional-debt-field">
                <label for="chaveiro">Valor do chaveiro (R$)</label>
                <input id="chaveiro" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                <label class="check-inline"><input id="semChaveiro" type="checkbox" /> Não há valor de chaveiro</label>
                <div class="explain-box" id="explicaChaveiro">O valor informado é acrescentado diretamente ao total cobrado do inquilino. Não há cálculo de ADM ou repasse ao proprietário sobre o chaveiro.</div>
              </div>
            </div>
          </div>
            </div>
            <div class="optional-topics-column">
<div class="card compact-topic-card" id="topicoManutencao">
            <div class="section-heading">
              <div class="section-number">5</div>
              <div>
                <h2 class="section-title">Manutenção</h2>
                <p class="section-subtitle">Informe o valor da manutenção que será acrescentado ao total da rescisão.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaManutencao">?</button><button class="btn-mini" type="button" data-clear-topic="manutencao">Limpar tópico</button></div>
              </div>
            </div>

            <div class="grid">
              <div class="field full optional-debt-field">
                <label for="manutencao">Valor da manutenção (R$)</label>
                <input id="manutencao" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                <label class="check-inline"><input id="semManutencao" type="checkbox" /> Não há manutenção/melhorias</label>
                <div class="explain-box" id="explicaManutencao">O valor informado aqui é somado diretamente ao total cobrado do inquilino.</div>
                
              </div>
            </div>
          </div>
<div class="card compact-topic-card" id="topicoSeguros">
            <div class="section-heading">
              <div class="section-number">7</div>
              <div>
                <h2 class="section-title">Seguros</h2>
                <p class="section-subtitle">Os valores informados abaixo são cobrados do inquilino e não são repassados ao proprietário.</p>
                <div class="topic-tools"><button class="help-btn" type="button" data-help="explicaSeguros">?</button><button class="btn-mini" type="button" data-clear-topic="seguros">Limpar tópico</button></div>
              </div>
            </div>

            <div class="grid insurance-grid">
              <div class="field full insurance-options-head">
                <label class="check-inline"><input id="semSeguros" type="checkbox" /> Não há seguros neste contrato</label>
                <span class="hint">Informe o valor de uma parcela. Ela é cobrada sempre nos dias finais e no aluguel inteiro. Use “Parcelas extras” quando houver mais parcelas além da primeira.</span>
                <div class="explain-box" id="explicaSeguros">Os seguros não têm ADM nem repasse ao proprietário. Cada valor informado é tratado como uma parcela. Use “Parcelas extras” para informar quantas parcelas adicionais serão cobradas. A cobrança dos seguros é automática nos dias finais e no aluguel inteiro.</div>
              </div>

              <div class="field optional-debt-field insurance-card-field">
                <div class="insurance-field-title"><label for="seguroIncendio">Seguro incêndio — valor da parcela (R$)</label><span class="insurance-count" id="resumoParcelasIncendio">1 parcela</span></div>
                <input id="seguroIncendio" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                <div class="insurance-extra-row">
                  <label for="seguroIncendioExtras">Parcelas extras</label>
                  <input id="seguroIncendioExtras" type="number" min="0" step="1" value="0" inputmode="numeric" />
                </div>
                <span class="hint" id="resumoCobrancaIncendio">Cobrança automática: dias finais + aluguel inteiro · R$ 0,00</span>
              </div>

              <div class="field optional-debt-field insurance-card-field">
                <div class="insurance-field-title"><label for="seguroFianca">Seguro fiança — valor da parcela (R$)</label><span class="insurance-count" id="resumoParcelasFianca">1 parcela</span></div>
                <input id="seguroFianca" type="text" inputmode="numeric" autocomplete="off" data-money="true" value="R$ 0,00" />
                <div class="insurance-extra-row">
                  <label for="seguroFiancaExtras">Parcelas extras</label>
                  <input id="seguroFiancaExtras" type="number" min="0" step="1" value="0" inputmode="numeric" />
                </div>
                <span class="hint" id="resumoCobrancaFianca">Cobrança automática: dias finais + aluguel inteiro · R$ 0,00</span>
              </div>
            </div>
          </div>
            </div>
          </div>
        </section>

        <aside>
          <div class="card result-box">
            <div class="result-head">
              <h2>Resumo da rescisão</h2>
              <p>Confira cada componente antes de concluir o atendimento.</p>
            </div>

            <div class="main-total">
              <span>TOTAL A PAGAR PELO INQUILINO</span>
              <strong id="totalGeral">R$ 0,00</strong>
            </div>

            <div class="sub-total"><span>Dias finais</span><strong id="resumoProporcionais">R$ 0,00</strong></div>
            <div class="sub-total"><span>Aviso prévio</span><strong id="resumoAviso">R$ 0,00</strong></div>
            <div class="sub-total"><span>Multa rescisória</span><strong id="resumoMulta">R$ 0,00</strong></div>
            <div class="sub-total"><span>Aluguel inteiro</span><strong id="resumoAluguelInteiro">R$ 0,00</strong></div>
            <div class="sub-total"><span>Manutenção</span><strong id="resumoManutencao">R$ 0,00</strong></div>
            <div class="sub-total"><span>Chaveiro</span><strong id="resumoChaveiro">R$ 0,00</strong></div>
            <div class="sub-total"><span>Seguros</span><strong id="resumoSegurosCompacto">R$ 0,00</strong></div>

            <div class="finance-toggle-wrap">
              <button class="finance-toggle" id="btnFinanceDetails" type="button" aria-expanded="false"><span>Ver ADM, repasses e detalhes financeiros</span><span>＋</span></button>
            </div>
            <div class="finance-details is-collapsed" id="financeDetails">
              <div class="sub-total"><span>ADM sobre dias finais</span><strong id="resumoAdmDiasFinais">R$ 0,00</strong></div>
              <div class="sub-total"><span>Repasse dos dias finais ao proprietário</span><strong id="resumoRepasseDiasFinais">R$ 0,00</strong></div>
              <div class="sub-total"><span>ADM sobre multa</span><strong id="resumoAdmMulta">R$ 0,00</strong></div>
              <div class="sub-total"><span>Repasse da multa ao proprietário</span><strong id="resumoRepasseMulta">R$ 0,00</strong></div>
              <div class="sub-total"><span>ADM do aluguel</span><strong id="resumoAdmAluguel">R$ 0,00</strong></div>
              <div class="sub-total"><span>Repasse do aluguel ao proprietário</span><strong id="resumoRepasseAluguel">R$ 0,00</strong></div>
              <div class="sub-total"><span>Seguro incêndio (não repassado)</span><strong id="resumoIncendio">R$ 0,00</strong></div>
              <div class="sub-total"><span>Seguro fiança (não repassado)</span><strong id="resumoFianca">R$ 0,00</strong></div>

              <div class="summary-note">
              <strong>Critério da multa:</strong> <span id="resumoModo">—</span><br />
              <strong>Período:</strong> <span id="resumoPeriodo">não informado</span><br />
              <strong>Dias corridos:</strong> <span id="resumoDias">0</span><br />
              <strong>Dias faltantes:</strong> <span id="resumoDiasFaltantes">—</span>
              </div>
            </div>

            <div class="workflow-status-card" id="workflowStatusCard">
              <div class="workflow-status-copy">
                <span class="workflow-status-label">Status da rescisão</span>
                <strong id="statusAtualLabel">Rascunho</strong>
                <span class="workflow-status-hint" id="statusAtualHint">Comece o cálculo e salve quando estiver pronto.</span>
              </div>
              <select id="statusRescisao" aria-label="Status da rescisão">
                <option>Rascunho</option>
                <option>Em conferência</option>
                <option>Conferido</option>
                <option>Pronto para cobrança</option>
                <option>Cobrado</option>
                <option>Cancelado</option>
              </select>
            </div>

            <div class="buttons">
              <button class="btn-primary" id="btnImprimir" type="button">Imprimir / PDF</button>
              <button class="btn-light" id="btnVerDetalhes" type="button" title="Abrir relatório completo">Detalhes</button>
              <button class="btn-secondary" id="btnSalvarHistorico" type="button">Salvar no histórico</button>
              <button class="btn-light" id="btnNovaRescisao" type="button">Nova rescisão</button>
              <button class="btn-light" id="btnLimpar" type="button">Limpar campos</button>
            </div>

            <div class="mini-collapse is-collapsed" id="internalCollapse">
              <div class="mini-collapse-head">
                <div><strong>Conferência interna da imobiliária</strong><p>ADM e repasses internos da imobiliária.</p></div>
                <button class="mini-collapse-toggle" type="button" data-collapse-target="internalCollapse">Mostrar</button>
              </div>
              <div class="mini-collapse-body">
                <div class="internal-card" id="conferenciaInterna" style="margin-top:0;">
                  <p>Estas informações são internas e não aparecem no modelo de e-mail do inquilino.</p>
              <div class="sub-total"><span>ADM dos dias finais</span><strong id="confAdmDiasFinais">R$ 0,00</strong></div>
              <div class="sub-total"><span>ADM do aviso prévio</span><strong id="confAdmAviso">R$ 0,00</strong></div>
              <div class="sub-total"><span>ADM da multa</span><strong id="confAdmMulta">R$ 0,00</strong></div>
              <div class="sub-total"><span>ADM do aluguel inteiro</span><strong id="confAdmAluguel">R$ 0,00</strong></div>
              <div class="internal-total"><span>TOTAL DE ADM</span><strong id="confTotalAdm">R$ 0,00</strong></div>
                  <div class="internal-total"><span>TOTAL LÍQUIDO / REPASSE</span><strong id="confTotalRepasse">R$ 0,00</strong></div>
                </div>
              </div>
            </div>
<div class="history-card card" id="historyCard">
              <div class="history-head">
                <div class="history-title-wrap">
                  <div class="history-title-icon" aria-hidden="true">↺</div>
                  <div>
                    <h3>Histórico de rescisões</h3>
                    <p>Consulte, abra ou duplique cálculos salvos no servidor central.</p>
                  </div>
                </div>
                <div class="history-head-actions">
                  <button class="btn-light history-direct-back" id="btnVoltarCalculadoraHistorico" type="button">← Calculadora</button>
                  <button class="btn-light" id="btnAtualizarHistorico" type="button">↻ Atualizar</button>
                  <button class="btn-light" id="btnLimparHistorico" type="button">Limpar histórico</button>
                </div>
              </div>
              <div class="history-stats">
                <div class="history-stat"><span class="history-stat-label">Registros</span><strong id="historyStatQtd">0</strong></div>
                <div class="history-stat"><span class="history-stat-label">Valor acumulado</span><strong id="historyStatTotal">R$ 0,00</strong></div>
                <div class="history-stat"><span class="history-stat-label">Último registro</span><strong id="historyStatUltimo">—</strong></div>
              </div>
              <div class="history-toolbar">
                <div class="history-search"><span class="history-search-icon">⌕</span><input id="buscaHistorico" type="search" placeholder="Buscar por inquilino ou imóvel..." autocomplete="off" /></div>
                <button class="btn-light" id="btnDuplicarUltimo" type="button">↗ Duplicar último</button>
              </div>
              <div class="history-results-note" id="historyResultsNote">Mostrando todos os registros</div>
              <div id="historyList" class="history-list-modern"><div class="history-empty"><div class="history-empty-icon">▣</div>Nenhuma rescisão salva.</div></div>
              <div class="saved-note">🔒 O histórico é compartilhado entre os computadores autorizados da imobiliária.</div>
            </div>

            <div class="mini-collapse is-collapsed" id="conferenceCollapse">
              <div class="mini-collapse-head">
                <div><strong>Conferência final antes da cobrança</strong><p>Revise os pontos e confirme antes de liberar o e-mail.</p></div>
                <button class="mini-collapse-toggle" type="button" data-collapse-target="conferenceCollapse">Mostrar</button>
              </div>
              <div class="mini-collapse-body">
                <div class="conference-final" id="conferenceFinal" style="margin-top:0;">
                  <div class="conference-status" id="conferenceFinalStatus">Há itens que precisam ser revisados.</div>
                  <table class="conference-table"><tbody id="conferenceTableBody"></tbody></table>
                  <div class="conference-actions">
                    <button class="btn-primary" id="btnConfirmarConferencia" type="button">Confirmar conferência</button>
                    <button class="btn-light" id="btnDesbloquearConferencia" type="button">Revisar novamente</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="mini-collapse is-collapsed" id="emailCollapse">
              <div class="mini-collapse-head">
                <div><strong>Modelo de e-mail para o inquilino</strong><p>Gera o texto automaticamente com os débitos calculados.</p></div>
                <button class="mini-collapse-toggle" type="button" data-collapse-target="emailCollapse">Mostrar</button>
              </div>
              <div class="mini-collapse-body">
            <div class="email-model card" id="emailModel" style="box-shadow:none; padding:16px;">
              <div class="section-heading" style="margin-bottom:12px;">
                <div class="section-number">✉</div>
                <div>
                  <h3 class="section-title">Modelo de e-mail para o inquilino</h3>
                  <p class="section-subtitle">Mostra somente os débitos que serão cobrados. Informações de ADM não aparecem no e-mail.</p>
                </div>
              </div>
              <div class="email-fields">
                <div class="field">
                  <label for="nomeInquilino">Nome do inquilino</label>
                  <input id="nomeInquilino" type="text" placeholder="Ex.: João da Silva" autocomplete="off" />
                </div>
                <div class="field">
                  <label for="cpfInquilino">CPF do inquilino <span class="hint">(opcional)</span></label>
                  <input id="cpfInquilino" type="text" placeholder="Ex.: 000.000.000-00" autocomplete="off" />
                </div>
                <div class="field">
                  <label for="enderecoImovel">Endereço do imóvel <span class="hint">(opcional)</span></label>
                  <input id="enderecoImovel" type="text" placeholder="Ex.: Rua, número, bairro - cidade - SP - CEP" autocomplete="off" />
                </div>
                <div class="field">
                  <label for="assuntoEmail">Assunto do e-mail</label>
                  <input id="assuntoEmail" type="text" value="Declaração de valores em aberto - encerramento da locação" />
                </div>
              </div>
              <textarea id="modeloEmail" class="email-preview" readonly aria-label="Modelo de e-mail para o inquilino"></textarea>
              <div class="email-actions">
                <button class="btn-primary" id="btnCopiarEmail" type="button" disabled>Copiar e-mail</button>
                <button class="btn-light" id="btnEditarEmail" type="button">Editar texto</button>
                <button class="btn-light" id="btnAtualizarEmail" type="button">Reverter para modelo</button>
              </div>
              <div class="edit-email-note" id="emailEditNote">O texto pode ser editado antes do envio. O conteúdo automático nunca inclui ADM ou repasses internos.</div>
            </div>

            </div>
            </div>

            <p class="note">O sistema aplica as fórmulas informadas. Antes da cobrança, confira as cláusulas do contrato e os critérios internos utilizados pela imobiliária.</p>
          </div>
        </aside>
      </div>
    </section>
  </main>

  <script>
window.USUARIO_LOGADO = <?= json_encode([
      'id' => (int)$usuario['id'],
      'nome' => $usuario['nome'],
      'login' => $usuario['login'],
      'perfil' => $usuario['perfil']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.CSRF_TOKEN = <?= json_encode($csrf) ?>;
    window.HISTORICO_INICIAL = <?= json_encode($historicoInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.HISTORICO_INICIAL_ID = <?= (int)$historicoInicialId ?>;
</script>
<script src="assets/js/pages/calculadora.js?v=money-mask-20261002"></script>
