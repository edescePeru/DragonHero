const hexToRgb = value => {
  const match = /^#([0-9A-F]{6})$/i.exec(value.trim());
  if (!match) return null;
  return [0, 2, 4].map(offset => parseInt(match[1].slice(offset, offset + 2), 16)).join(', ');
};
const opacity = value => { const n = Number(value); if (!Number.isFinite(n) || n < 0 || n > 100) return '0'; return n === 0 ? '0' : n === 100 ? '1' : String(n / 100); };
document.querySelectorAll('[data-rarity-visual-form]').forEach(form => {
  const previews = form.querySelectorAll('[data-rarity-preview]');
  const text = kind => form.querySelector(`[data-rarity-color-text="${kind}"]`);
  const picker = kind => form.querySelector(`[data-rarity-color-picker="${kind}"]`);
  const number = kind => form.querySelector(`[data-rarity-number="${kind}"]`);
  const range = kind => form.querySelector(`[data-rarity-range="${kind}"]`);
  const px = kind => form.querySelector(`[data-rarity-px="${kind}"]`);
  const update = () => {
    const border = hexToRgb(text('border').value), glow = hexToRgb(text('glow').value);
    previews.forEach(node => {
      if (border) node.style.setProperty('--rarity-border-rgb', border);
      if (glow) node.style.setProperty('--rarity-glow-rgb', glow);
      node.style.setProperty('--rarity-border-opacity', opacity(number('border-opacity').value));
      node.style.setProperty('--rarity-border-width', `${px('border-width').value}px`);
      node.style.setProperty('--rarity-glow-opacity', opacity(number('glow-opacity').value));
      node.style.setProperty('--rarity-glow-blur', `${px('glow-blur').value}px`);
      node.style.setProperty('--rarity-glow-spread', `${px('glow-spread').value}px`);
    });
    form.querySelector('[data-rarity-visual-warning]').classList.toggle('d-none', Number(number('glow-opacity').value) < 75 && !(Number(px('glow-blur').value) === 40 && Number(px('glow-spread').value) === 20));
  };
  ['border','glow'].forEach(kind => { picker(kind).addEventListener('input',()=>{text(kind).value=picker(kind).value.toUpperCase();update();});text(kind).addEventListener('input',()=>{const value=text(kind).value.toUpperCase();text(kind).value=value;if(hexToRgb(value))picker(kind).value=value;update();}); });
  ['border-opacity','glow-opacity'].forEach(kind => { range(kind).addEventListener('input',()=>{number(kind).value=range(kind).value;update();});number(kind).addEventListener('input',()=>{range(kind).value=number(kind).value;update();}); });
  ['border-width','glow-blur','glow-spread'].forEach(kind => px(kind).addEventListener('input',update));
  form.querySelector('[data-rarity-copy-border]').addEventListener('click',()=>{text('glow').value=text('border').value;picker('glow').value=picker('border').value;update();});
});
