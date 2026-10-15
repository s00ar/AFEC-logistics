const finder = document.querySelector('#commodity-finder');

if (finder) {
  const toggle = finder.querySelector('#commodity-search-toggle');
  const panel = finder.querySelector('#commodity-search-panel');
  const input = finder.querySelector('#commodity-search-input');
  const status = finder.querySelector('#commodity-search-status');
  const previous = finder.querySelector('[data-search-previous]');
  const next = finder.querySelector('[data-search-next]');
  const normalize = value => value.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('es').trim();
  const materials = [...document.querySelectorAll('[data-commodity-search]')].map(element => ({
    element, name: normalize(element.dataset.commoditySearch),
  }));
  let matches = [];
  let current = -1;

  function select(index) {
    if (!matches.length) return;
    matches[current]?.classList.remove('commodity-search-current');
    current = (index + matches.length) % matches.length;
    const row = matches[current];
    row.classList.add('commodity-search-current');
    status.textContent = `${current + 1} de ${matches.length} ${matches.length === 1 ? 'coincidencia' : 'coincidencias'}`;
    // Scroll to the material name so a horizontally scrolled table finds its first column too.
    (row.querySelector('th, h2') || row).scrollIntoView({
      block: 'center', inline: 'nearest',
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    });
  }

  function search() {
    for (const { element } of materials) element.classList.remove('commodity-search-match', 'commodity-search-current');
    const words = normalize(input.value).split(/\s+/u).filter(Boolean);
    matches = words.length ? materials.filter(({ name }) => words.every(word => name.includes(word))).map(({ element }) => element) : [];
    current = -1;
    for (const row of matches) row.classList.add('commodity-search-match');
    previous.disabled = next.disabled = matches.length < 2;
    status.textContent = words.length ? 'Sin coincidencias en esta página.' : 'Escribí para buscar.';
    if (matches.length) select(0);
  }

  function close() {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    input.value = '';
    search();
    toggle.focus({ preventScroll: true });
  }

  toggle.addEventListener('click', () => {
    if (!panel.hidden) { close(); return; }
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    input.focus({ preventScroll: true });
  });
  finder.querySelector('[data-search-close]').addEventListener('click', close);
  input.addEventListener('input', search);
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); select(current + (event.shiftKey ? -1 : 1)); }
  });
  finder.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); close(); }
  });
  previous.addEventListener('click', () => select(current - 1));
  next.addEventListener('click', () => select(current + 1));
  finder.hidden = false;
}
