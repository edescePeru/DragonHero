const navigationActionTypes = ['map', 'world', 'region', 'internal_route'];

const initializeWorldMapPlayer = player => {
  if (player.dataset.worldMapPlayerInitialized === 'true') return;
  player.dataset.worldMapPlayerInitialized = 'true';

  const image = player.querySelector('[data-world-map-player-image]');
  const svg = player.querySelector('[data-world-map-player-svg]');
  const canvas = player.querySelector('[data-world-map-player-canvas]');
  const status = player.querySelector('[data-world-map-status]');
  const panel = player.querySelector('[data-world-map-panel]');

  if (!image || !svg || !canvas || !status || !panel) {
    player.dataset.playerState = 'error';
    return;
  }

  const title = panel.querySelector('[data-panel-title]');
  const body = panel.querySelector('[data-panel-body]');
  const link = panel.querySelector('[data-panel-link]');
  let imageResolved = false;

  const clear = node => {
    while (node.firstChild) node.removeChild(node.firstChild);
  };
  const text = (tag, value, className) => {
    const node = document.createElement(tag);
    node.textContent = value;
    if (className) node.className = className;
    return node;
  };
  const setState = (state, message) => {
    player.dataset.playerState = state;
    canvas.classList.toggle('is-ready', state === 'ready');
    canvas.classList.toggle('is-error', state === 'error');
    status.classList.toggle('d-none', state === 'ready');
    status.textContent = message;
  };
  const resolveImage = loaded => {
    if (imageResolved) return;
    imageResolved = true;
    setState(loaded ? 'ready' : 'error', loaded ? '' : 'No se pudo cargar la imagen del mapa.');
  };
  const postButton = (url, label, style) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.className = 'd-inline-block me-2 mt-2';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = player.dataset.csrf;
    const button = document.createElement('button');
    button.className = style;
    button.textContent = label;
    form.appendChild(token);
    form.appendChild(button);
    return form;
  };
  const renderPanel = data => {
    clear(body);
    if (data.description) body.appendChild(text('p', data.description));
    if (data.recommended_level_min) {
      const maximum = data.recommended_level_max ? '–' + data.recommended_level_max : '+';
      body.appendChild(text('p', 'Nivel recomendado: ' + data.recommended_level_min + maximum, 'small text-secondary'));
    }
    (data.monsters || []).forEach(monster => {
      body.appendChild(text('h3', monster.name + ' · nivel ' + monster.level, 'h6'));
      const loot = (monster.loot || []).map(item => item.item_name + ' (' + (item.chance_basis_points / 100) + ' %)').join(', ');
      body.appendChild(text('p', loot ? 'Loot: ' + loot : 'Sin loot visible.', 'small'));
    });
    if (data.automatic_hunting_available) {
      body.appendChild(text('h3', 'Cacería automática', 'h6 mt-3 mb-1'));
      body.appendChild(text('p', 'El servidor ejecutará y resolverá los encuentros automáticamente.', 'small text-secondary mb-1'));
      body.appendChild(postButton(data.automatic_hunting_url, 'Cacería automática', 'btn btn-success'));
    }
    if (data.manual_combat_available) {
      body.appendChild(text('h3', 'Combate manual', 'h6 mt-3 mb-1'));
      body.appendChild(text('p', 'Elige objetivos y ejecuta las acciones de tu personaje.', 'small text-secondary mb-1'));
      body.appendChild(postButton(data.manual_combat_url, 'Combate manual', 'btn btn-primary'));
    }
  };
  const showPanel = data => {
    title.textContent = data.label || 'Destino';
    clear(body);
    link.classList.add('d-none');
    if (!data.enabled) {
      body.appendChild(text('p', data.availability_message || data.disabled_reason || 'Destino no disponible.', 'alert alert-warning'));
    } else if (data.panel_data) {
      renderPanel(data.panel_data);
    } else if (data.description) {
      body.appendChild(text('p', data.description));
    }
    panel.classList.remove('d-none');
    const help = player.querySelector('[data-map-help]');
    if (help) help.classList.add('d-none');
  };
  const selectArea = node => {
    player.querySelectorAll('[data-world-map-area]').forEach(area => {
      area.classList.remove('is-selected');
      area.setAttribute('aria-pressed', 'false');
    });
    node.classList.add('is-selected');
    node.setAttribute('aria-pressed', 'true');
  };
  const activate = node => {
    let data;
    try {
      data = JSON.parse(node.dataset.action);
    } catch (error) {
      showPanel({ enabled: false, label: 'Destino no disponible', disabled_reason: 'La configuración del destino no es válida.' });
      return;
    }
    if (!data.enabled) {
      selectArea(node);
      showPanel(data);
      return;
    }
    if (navigationActionTypes.indexOf(data.action_type) !== -1 && data.navigation_url) {
      window.location.assign(data.navigation_url);
      return;
    }
    selectArea(node);
    showPanel(data);
  };

  player.querySelectorAll('[data-world-map-area]').forEach(node => {
    node.addEventListener('click', () => activate(node));
    node.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        activate(node);
      }
    });
  });

  image.addEventListener('load', () => resolveImage(true), { once: true });
  image.addEventListener('error', () => resolveImage(false), { once: true });
  if (image.complete) resolveImage(image.naturalWidth > 0 && image.naturalHeight > 0);
};

document.querySelectorAll('[data-world-map-player]').forEach(initializeWorldMapPlayer);
