<div id="commodity-finder" class="commodity-finder" hidden>
    <section id="commodity-search-panel" class="commodity-finder-panel" role="search" aria-label="Buscar mercancías" hidden>
        <div class="dialog-heading"><h2>Buscar mercancía</h2><button type="button" class="button-quiet" data-search-close aria-label="Cerrar buscador">×</button></div>
        <label for="commodity-search-input">Nombre de mercancía</label>
        <input id="commodity-search-input" type="search" placeholder="Ej.: acero" autocomplete="off" aria-describedby="commodity-search-help" aria-controls="main">
        <p id="commodity-search-help">Busca en los materiales desplegados de esta página. Enter: siguiente · Mayús + Enter: anterior.</p>
        <div class="commodity-finder-navigation">
            <button type="button" class="button-quiet" data-search-previous aria-label="Mercancía anterior" disabled>↑</button>
            <output id="commodity-search-status" aria-live="polite" aria-atomic="true">Escribí para buscar.</output>
            <button type="button" class="button-quiet" data-search-next aria-label="Mercancía siguiente" disabled>↓</button>
        </div>
    </section>
    <button id="commodity-search-toggle" type="button" class="button-primary commodity-finder-toggle" aria-label="Buscar mercancía" title="Buscar mercancía" aria-expanded="false" aria-controls="commodity-search-panel">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
    </button>
</div>
