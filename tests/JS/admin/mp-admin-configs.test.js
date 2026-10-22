const { createAdminRuntime: createRuntime } = require('./helpers/admin-runtime');
const { element, fragment } = require('./fixtures/settings-markup');

const prefix = 'woocommerce_woo-mercado-pago-custom_';
const credentials = ['access_token_prod', 'access_token_test', 'public_key_prod', 'public_key_test'];
const settings = (enabled = true) =>
  fragment(
    '\n  ',
    element('div', { class: 'wc-admin-breadcrumb' }, 'Breadcrumb'),
    element('div', { class: 'mp-settings-header-logo' }, 'Logo'),
    '\n  ',
    element(
      'form',
      { id: 'mainform', class: 'woocommerce' },
      element('h2', {}, 'Gateway'),
      element('p', {}, 'Description'),
      '\n    ',
      element(
        'table',
        { class: 'form-table' },
        element(
          'tbody',
          {},
          '\n      ',
          element(
            'tr',
            {},
            element(
              'th',
              { class: 'titledesc' },
              element('label', {}, 'Title', element('span', {}, 'Subtitle')),
            ),
            '\n        ',
            element(
              'td',
              {},
              element('input', { class: 'limit-title-max-length' }),
              element('select', {}, element('option', {}, 'One')),
            ),
          ),
          '\n      ',
          element(
            'tr',
            {},
            element('td', {}, element('span', { class: 'mp-hidden-field-description' }, 'Hidden')),
          ),
          '\n      ',
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', { type: 'checkbox', class: 'mp-selectall', 'data-group': 'cards' }),
              '\n        ',
              element('input', { type: 'checkbox', class: 'mp-child', 'data-group': 'cards', checked: true }),
              '\n        ',
              element('input', { type: 'checkbox', class: 'mp-child', 'data-group': 'cards' }),
              '\n        ',
              element('input', { type: 'checkbox', class: 'mp-selectall', 'data-group': 'offline' }),
              '\n        ',
              element('input', { type: 'checkbox', class: 'mp-child', 'data-group': 'offline' }),
            ),
          ),
          '\n      ',
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', {
                type: 'checkbox',
                id: 'woocommerce_woo-mercado-pago-custom_subscriptions_enabled',
                checked: enabled,
              }),
            ),
          ),
          '\n      ',
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', {
                id: 'woocommerce_woo-mercado-pago-custom_subscriptions_access_token_prod',
                value: 'synthetic-value',
              }),
            ),
          ),
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', {
                id: 'woocommerce_woo-mercado-pago-custom_subscriptions_access_token_test',
                value: 'synthetic-value',
              }),
            ),
          ),
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', {
                id: 'woocommerce_woo-mercado-pago-custom_subscriptions_public_key_prod',
                value: 'synthetic-value',
              }),
            ),
          ),
          element(
            'tr',
            {},
            element(
              'td',
              {},
              element('input', {
                id: 'woocommerce_woo-mercado-pago-custom_subscriptions_public_key_test',
                value: 'synthetic-value',
              }),
            ),
          ),
          '\n      ',
          element('tr', { class: 'mp-subscriptions-group' }, element('td', {}, 'Subscriptions information')),
          '\n    ',
        ),
      ),
      '\n    ',
      element('h3', { id: 'woocommerce_woo-mercado-pago-custom_advanced_configuration_title' }, 'Advanced'),
      '\n    ',
      element(
        'p',
        { id: 'woocommerce_woo-mercado-pago-custom_advanced_configuration_description' },
        'Description',
      ),
      element(
        'table',
        { id: 'advanced-table' },
        element('tbody', {}, element('tr', {}, element('td', {}, 'Extra'))),
      ),
      '\n    ',
      element('p', { class: 'submit' }, element('button', { class: 'woocommerce-save-button' }, 'Save')),
      '\n  ',
    ),
  );

describe.each(['source', 'minified'])('Admin gateway configuration — real load and event handlers (%s)', assetVariant => {
  const createAdminRuntime = (markup, globals) => createRuntime(markup, globals, assetVariant);
  let runtime;
  beforeEach(() => jest.useFakeTimers());
  afterEach(() => runtime.cleanup());

  function start(markup = settings(), params = {}) {
    runtime = createAdminRuntime(markup, { window: { wc_mercadopago_admin_components_params: params } });
    runtime.load('mp-admin-configs.js');
    runtime.dispatchLoad();
  }
  const toggle = () => document.querySelector('[id$="subscriptions_enabled"]');
  const rows = () => credentials.map(name => document.querySelector(`[id$="subscriptions_${name}"]`).closest('tr'));

  it('applies constraints and styles to the real settings table on load', () => {
    start();
    expect(document.querySelector('.limit-title-max-length')).toHaveAttribute('maxlength', '85');
    expect(document.querySelector('th.titledesc')).toHaveClass('mp-field-text-title');
    expect(document.querySelector('th.titledesc span')).toHaveClass('mp-field-text-subtitle');
    expect(document.querySelector('.woocommerce-save-button')).toHaveClass('mp-primary-button');
    expect(document.querySelector('select')).toHaveClass('mp-select');
    expect(document.querySelector('input[type=checkbox]')).toHaveClass('mp-checkbox');
    expect(document.querySelector('.wc-admin-breadcrumb').style.display).toBe('none');
    expect(document.querySelector('.mp-settings-header-logo').style.display).toBe('none');
    expect(document.querySelector('.mp-hidden-field-description').closest('tr').style.display).toBe('none');
  });

  it('hides the legacy unclassified heading and description when there is no logo', () => {
    const markup = settings();
    markup.querySelector('.mp-settings-header-logo').remove();
    start(markup);
    expect(document.querySelector('#mainform > h2').style.display).toBe('none');
    expect(document.querySelector('#mainform > p').style.display).toBe('none');
    expect(document.querySelector('#mainform > p.submit').style.display).not.toBe('none');
  });

  it('does not hide a heading with its own class', () => {
    const markup = settings();
    markup.querySelector('.mp-settings-header-logo').remove();
    markup.querySelector('h2').classList.add('other-heading');
    start(markup);
    expect(document.querySelector('#mainform > h2').style.display).not.toBe('none');
  });

  it('sets group selection from actual children and changes only the matching group', () => {
    start();
    const cards = document.querySelector('.mp-selectall[data-group="cards"]');
    const offline = document.querySelector('.mp-child[data-group="offline"]');
    expect(cards.checked).toBe(true);
    cards.click();
    expect([...document.querySelectorAll('.mp-child[data-group="cards"]')].map(el => el.checked)).toEqual([false, false]);
    cards.click();
    expect([...document.querySelectorAll('.mp-child[data-group="cards"]')].map(el => el.checked)).toEqual([true, true]);
    expect(offline.checked).toBe(false);
    document.querySelector('.mp-child[data-group="cards"]').click();
    expect(cards.checked).toBe(true);
    document.querySelectorAll('.mp-child[data-group="cards"]')[1].click();
    expect(cards.checked).toBe(false);
  });

  it('enables the native unsaved-config prompt only after a change', () => {
    start();
    expect(runtime.window.onbeforeunload).toBe('');
    document.querySelector('select').dispatchEvent(new Event('change', { bubbles: true }));
    const event = new Event('beforeunload', { cancelable: true });
    expect(runtime.window.onbeforeunload(event)).toBe(true);
    expect(event.defaultPrevented).toBe(true);
  });

  it('expands and collapses advanced settings through its registered click handler', () => {
    start();
    const heading = document.querySelector('[id$="advanced_configuration_title"]');
    const description = document.querySelector('[id$="advanced_configuration_description"]');
    const table = document.getElementById('advanced-table');
    expect(table.style.display).toBe('none');
    heading.click();
    expect(table.style.display).toBe('block');
    expect(description.style.display).toBe('block');
    expect(document.getElementById('header_plus').style.display).toBe('none');
    expect(document.getElementById('header_less').style.display).toBe('block');
    heading.click();
    expect(table.style.display).toBe('none');
    expect(description.style.display).toBe('none');
    expect(document.getElementById('header_plus').style.display).toBe('block');
  });

  it('keeps an incomplete advanced-settings component untouched', () => {
    const markup = settings();
    markup.querySelector(`[id="${prefix}advanced_configuration_description"]`).remove();
    start(markup);
    expect(document.getElementById('advanced-table').style.display).toBe('');
    expect(document.getElementById('header_plus')).toBeNull();
  });

  it('removes adjacent headings and save controls for an empty table', () => {
    start(fragment(
  element("form", {},
    element("h2", { "id": "heading" }, "Unavailable"),
    element("p", { "id": "description" }, "Description"),
    element("table", { "class": "form-table" }),
    element("p", { "id": "save" }, "Save")
  )
));
    expect(document.getElementById('heading')).toBeNull();
    expect(document.getElementById('description')).toBeNull();
    expect(document.getElementById('save')).toBeNull();
  });

  it('leaves an unrelated screen without a table intact', () => {
    start(fragment(element("div", { "id": "other" }, "Other settings")));
    expect(document.getElementById('other').textContent).toBe('Other settings');
  });

  it('hides subscription credentials initially and reveals them on enable', () => {
    start(settings(false));
    rows().forEach(row => expect(row.style.display).toBe('none'));
    expect(document.querySelector('.mp-subscriptions-group').style.display).toBe('none');
    toggle().click();
    rows().forEach(row => expect(row.style.display).toBe(''));
    expect(document.querySelector('.mp-subscriptions-modal')).toBeNull();
  });

  it.each(['keep', 'Escape'])('keeps subscriptions enabled when disable is cancelled with %s', choice => {
    start();
    toggle().click();
    expect(toggle().checked).toBe(true);
    const overlay = document.querySelector('.mp-subscriptions-modal-overlay');
    expect(document.querySelector('.mp-subscriptions-modal')).toHaveAttribute('role', 'dialog');
    expect(document.activeElement.id).toBe('mp-subs-modal-keep');
    if (choice === 'keep') document.getElementById('mp-subs-modal-keep').click();
    else overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(toggle().checked).toBe(true);
    rows().forEach(row => expect(row.style.display).toBe(''));
    expect(document.querySelector('.mp-subscriptions-modal-overlay')).toBeNull();
    expect(document.activeElement).toBe(toggle());
  });

  it('disables subscriptions only after confirm and treats localized modal text as text', () => {
    start(settings(), { subscriptions_disable_modal_title: '<b>Disable?</b>', subscriptions_disable_modal_body: 'Recurring payments', subscriptions_disable_modal_keep: 'Keep', subscriptions_disable_modal_confirm: 'Disable' });
    toggle().click();
    expect(document.getElementById('mp-subs-modal-title').textContent).toBe('<b>Disable?</b>');
    expect(document.getElementById('mp-subs-modal-title').querySelector('b')).toBeNull();
    document.getElementById('mp-subs-modal-confirm').click();
    expect(toggle().checked).toBe(false);
    rows().forEach(row => expect(row.style.display).toBe('none'));
    expect(document.querySelector('.mp-subscriptions-group').style.display).toBe('none');
    expect(document.querySelector('.mp-subscriptions-modal-overlay')).toBeNull();
  });

  it('initializes gateway settings without a subscriptions toggle', () => {
    const markup = settings();
    markup.querySelector(`[id="${prefix}subscriptions_enabled"]`).remove();
    start(markup);
    expect(document.querySelector('.woocommerce-save-button')).toHaveClass('mp-primary-button');
    expect(document.querySelector('.mp-subscriptions-modal-overlay')).toBeNull();
  });
});
