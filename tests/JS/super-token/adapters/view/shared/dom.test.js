const { el } = require('@super-token/adapters/view/shared/dom');

describe('el (declarative DOM builder)', () => {
  it('Given only a tag, When built, Then it creates that element with no attributes or children', () => {
    const node = el('div');
    expect(node.tagName).toBe('DIV');
    expect(node.className).toBe('');
    expect(node.childNodes).toHaveLength(0);
  });

  it('Given classes, When built, Then they are added to the classList', () => {
    const node = el('span', { classes: ['a', 'b'] });
    expect(node.classList.contains('a')).toBe(true);
    expect(node.classList.contains('b')).toBe(true);
  });

  it('Given text, When built, Then it is assigned via textContent (never parsed as markup)', () => {
    const node = el('p', { text: '<b>x</b>' });
    expect(node.textContent).toBe('<b>x</b>');
    expect(node.querySelector('b')).toBeNull();
  });

  it('Given empty-string text, When built, Then textContent is set (undefined check, not falsy check)', () => {
    const node = el('p', { text: '' });
    expect(node.textContent).toBe('');
  });

  it('Given attrs, When built, Then each is applied via setAttribute', () => {
    const node = el('a', { attrs: { href: '/x', 'aria-label': 'go' } });
    expect(node.getAttribute('href')).toBe('/x');
    expect(node.getAttribute('aria-label')).toBe('go');
  });

  it('Given dataset entries, When built, Then defined keys are set and undefined keys are skipped', () => {
    const node = el('div', { dataset: { variant: 'v2', skip: undefined } });
    expect(node.dataset.variant).toBe('v2');
    expect('skip' in node.dataset).toBe(false);
  });

  it('Given children, When built, Then non-null children are appended in order and nulls are ignored', () => {
    const first = el('span', { text: '1' });
    const second = el('span', { text: '2' });
    const node = el('div', { children: [first, null, second] });
    expect(node.childNodes).toHaveLength(2);
    expect(node.firstChild).toBe(first);
    expect(node.lastChild).toBe(second);
  });
});
