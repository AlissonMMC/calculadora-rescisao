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
            filterForm.requestSubmit();
        };

        // Seletores atualizam o painel imediatamente.
        periodo?.addEventListener('change', enviarFiltros);
        status?.addEventListener('change', enviarFiltros);

        // Para o período personalizado, só consulta quando as duas datas
        // estiverem preenchidas, evitando duas recargas consecutivas.
        const observarDatas = () => {
            if (periodo?.value !== 'personalizado') return;

            if (dataInicial?.value && dataFinal?.value) {
                enviarFiltros();
            }
        };

        dataInicial?.addEventListener('change', observarDatas);
        dataFinal?.addEventListener('change', observarDatas);
    }
})();
