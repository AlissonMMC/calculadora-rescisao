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
})();
