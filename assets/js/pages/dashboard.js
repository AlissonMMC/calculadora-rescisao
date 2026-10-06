(() => {
    const input = document.getElementById('buscaRapida');
    const button = document.getElementById('btnBuscaRapida');
    const filterForm = document.querySelector('.dashboard-filters-form');

    if (input && button) {
        const atualizarLink = () => {
            const url = new URL(button.href, window.location.href);
            const termo = input.value.trim();

            if (termo) url.searchParams.set('q', termo);
            else url.searchParams.delete('q');

            button.href = url.toString();
        };

        input.addEventListener('input', atualizarLink);
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                atualizarLink();
                button.click();
            }
        });

        atualizarLink();
    }

    if (filterForm) {
        const periodo = document.getElementById('periodo');
        const status = document.getElementById('status');
        const dataInicial = document.getElementById('de');
        const dataFinal = document.getElementById('ate');

        const enviarFiltros = () => {
            if (typeof filterForm.requestSubmit === 'function') {
                filterForm.requestSubmit();
            } else {
                filterForm.submit();
            }
        };

        // Seletores atualizam o painel imediatamente.
        periodo?.addEventListener('change', enviarFiltros);
        status?.addEventListener('change', enviarFiltros);

        // Ao informar qualquer data, o filtro passa automaticamente
        // para "Período personalizado". Assim, as datas escolhidas
        // realmente participam da consulta no servidor.
        const observarDatas = () => {
            if (!periodo) return;

            if (dataInicial?.value || dataFinal?.value) {
                periodo.value = 'personalizado';
            }

            // Atualiza assim que as duas datas estiverem definidas.
            // Também permite pesquisar com apenas uma das extremidades.
            if (
                periodo.value === 'personalizado' &&
                (dataInicial?.value || dataFinal?.value)
            ) {
                enviarFiltros();
            }
        };

        dataInicial?.addEventListener('change', observarDatas);
        dataFinal?.addEventListener('change', observarDatas);
    }
})();
