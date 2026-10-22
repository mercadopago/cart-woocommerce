// Fixture construction only: real DOM nodes, with text kept as text and no HTML sink.
function element(tag, attributes = {}, ...children) {
  const node = document.createElement(tag);
  Object.entries(attributes).forEach(([name, value]) => {
    if (value !== false && value !== null && value !== undefined) {
      node.setAttribute(name, value === true ? '' : String(value));
    }
  });
  children.flat(Infinity).forEach((child) => {
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  });
  return node;
}

function fragment(...children) {
  const node = document.createDocumentFragment();
  children.flat(Infinity).forEach((child) => {
    node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
  });
  return node;
}

// Representative structure from templates/admin/settings/settings.php and credentials-*.php.
// Preserve text nodes as well as attributes: real handlers navigate this DOM.
function settingsMarkup(logCount = 9) {
  return element(
    'div',
    { class: 'mp-settings' },
    '\n    ',
    element('div', { id: 'mp-mode-badge', class: 'mp-settings-prod-mode-alert' }),
    '\n    ',
    element('div', { id: 'mp-orange-badge', class: 'mp-settings-alert-payment-methods-green' }),
    '\n    ',
    element('div', { id: 'mp-icon-badge', class: 'mp-settings-icon-success' }),
    '\n    ',
    element('div', { style: 'display:none' }, element('span', { id: 'mp-red-badge' })),
    '\n    ',
    element('div', { id: 'mp-helper-test' }),
    element('div', { id: 'mp-helper-prod' }),
    '\n    ',
    element('div', { id: 'mp-title-helper-test' }),
    element('div', { id: 'mp-title-helper-prod' }),
    '\n    ',
    element('div', { id: 'mp-mode-badge-test' }),
    element('div', { id: 'mp-mode-badge-prod' }),
    '\n    ',
    element('input', {
      id: 'mp-settings-testmode-test',
      type: 'radio',
      name: 'mp-test-prod',
      value: 'yes',
      checked: true,
    }),
    '\n    ',
    element('input', { id: 'mp-settings-testmode-prod', type: 'radio', name: 'mp-test-prod', value: 'no' }),
    '\n    ',
    element('button', { id: 'mp-store-mode-save', type: 'button' }, 'Save mode'),
    '\n    ',
    element(
      'div',
      { id: 'mp-settings-step-one' },
      element(
        'div',
        { class: 'mp-settings-title-align' },
        '\n      ',
        element(
          'span',
          { class: 'mp-settings-margin-left' },
          '\n',
          element('img', { id: 'mp-credentials-arrow-up' }),
          '\n',
        ),
        '\n    ',
      ),
      element('div', { id: 'mp-step-1', style: 'display:none' }),
    ),
    '\n    ',
    element(
      'div',
      { class: 'mp-settings-title-align' },
      element(
        'span',
        { class: 'mp-settings-margin-left' },
        '\n',
        element('img', { id: 'mp-store-info-arrow-up' }),
        '\n',
      ),
    ),
    element('div', { id: 'mp-step-2' }),
    '\n    ',
    element(
      'div',
      { class: 'mp-settings-title-align' },
      element(
        'span',
        { class: 'mp-settings-margin-left' },
        '\n',
        element('img', { id: 'mp-payments-arrow-up' }),
        '\n',
      ),
    ),
    element('div', { id: 'mp-step-3' }),
    '\n    ',
    element(
      'div',
      { class: 'mp-settings-title-align' },
      element(
        'span',
        { class: 'mp-settings-margin-left' },
        '\n',
        element('img', { id: 'mp-modes-arrow-up' }),
        '\n',
      ),
    ),
    element('div', { id: 'mp-step-4' }),
    '\n    ',
    element('button', { id: 'mp-payment-method-continue', type: 'button' }, 'Continue'),
    '\n    ',
    element(
      'div',
      { id: 'block-two' },
      element('button', { id: 'mp-advanced-options', type: 'button' }, 'Advanced'),
      element('div', { style: 'display:none' }, 'Options'),
    ),
    '\n    ',
    element('div', { id: 'mp-settings-icon-store', class: 'mp-settings-icon-store' }),
    '\n    ',
    element('div', { id: 'mp-settings-icon-payment', class: 'mp-settings-icon-payment' }),
    '\n    ',
    element('div', { id: 'mp-req-ssl', class: 'mp-settings-icon-success', style: 'display:none' }),
    '\n    ',
    element('div', { id: 'mp-req-gd', class: 'mp-settings-icon-success', style: 'display:none' }),
    '\n    ',
    element('div', { id: 'mp-payment' }),
    '\n    ',
    element('div', { class: 'mp-settings-payment-block' }, 'Stale payment method'),
    '\n    ',
    element('button', { id: 'mp-btn-credentials', type: 'button' }, 'Credentials'),
    '\n    ',
    element('button', { id: 'mp-settings-auto-data-consult', type: 'button' }, 'Show credentials'),
    '\n    ',
    element(
      'div',
      { id: 'mp-credentials-modal', style: 'display:flex' },
      '\n      ',
      element('button', { id: 'mp-credentials-close-modal', type: 'button' }, 'Close'),
      '\n      ',
      element('input', { id: 'mp-public-key-prod', value: 'synthetic-production-public-key' }),
      '\n      ',
      element('input', {
        id: 'mp-access-token-production',
        type: 'password',
        value: 'synthetic-production-value',
      }),
      '\n      ',
      element(
        'button',
        { id: 'mp-production-token-button', type: 'button' },
        element('span', { id: 'mp-production-token-img', class: 'mp-access-token-button-closed' }),
      ),
      '\n      ',
      element(
        'fieldset',
        { id: 'mp-test-public-key' },
        element('label', {}, 'Public key'),
        element('input', { id: 'mp-public-key-test', value: 'synthetic-public-key' }),
      ),
      '\n      ',
      element(
        'fieldset',
        { id: 'mp-test-access-token' },
        element('label', {}, 'Access token'),
        element('input', { id: 'mp-access-token-test', type: 'password', value: 'synthetic-access-token' }),
      ),
      '\n      ',
      element(
        'button',
        { id: 'mp-test-token-button', type: 'button' },
        element('span', { id: 'mp-test-token-img', class: 'mp-access-token-button-closed' }),
      ),
      '\n      ',
      element('div', { id: 'msg-info-credentials' }),
      element('button', { id: 'mp-btn-update-test-credentials', type: 'button' }, 'Save test credentials'),
      '\n    ',
    ),
    '\n    ',
    element('input', { id: 'mp-store-url-ipn', value: 'https://service.example.invalid/notify' }),
    '\n    ',
    element('input', { id: 'mp-store-url-ipn-options', type: 'checkbox', checked: true }),
    '\n    ',
    element('select', { id: 'mp-store-categories' }, element('option', { value: 'retail' }, 'Retail')),
    '\n    ',
    element('input', { id: 'mp-store-category-id', value: 'category-fixture' }),
    '\n    ',
    element('input', { id: 'mp-store-integrator-id', value: 'synthetic-integrator' }),
    '\n    ',
    element('input', { id: 'mp-store-identification', value: 'Synthetic shop' }),
    '\n    ',
    element('input', { id: 'mp-store-debug-mode', type: 'checkbox', value: 'yes', checked: true }),
    '\n    ',
    element('input', { id: 'mp-store-cron-config', value: 'no' }),
    '\n    ',
    element('button', { id: 'mp-store-info-save', type: 'button' }, 'Save store'),
    '\n    ',
    element('div', { id: 'mp-settings-credentials-linked' }, 'Linked'),
    '\n    ',
    element('button', { id: 'mp-switch-account-btn', type: 'button' }, 'Switch account'),
    '\n    ',
    element(
      'div',
      { id: 'mp-settings-credentials-country' },
      element(
        'div',
        { class: 'mp-input-group' },
        '\n      ',
        element(
          'div',
          { id: 'mp-credentials-country-select', class: 'mp-select', 'aria-expanded': 'false' },
          '\n        ',
          element('input', { type: 'hidden', id: 'mp-credentials-country', value: 'MLB' }),
          '\n        ',
          element('button', { type: 'button' }, 'Brasil'),
          element(
            'ul',
            { role: 'list', class: 'mp-hidden', tabindex: '-1' },
            '\n          ',
            element(
              'li',
              { role: 'option', 'data-value': 'MLB', 'aria-selected': 'true' },
              element('div', { class: 'mp-select-pipe' }),
              'Brasil',
            ),
            '\n          ',
            element(
              'li',
              { role: 'option', 'data-value': 'MLA', 'aria-selected': 'false' },
              element('div', { class: 'mp-select-pipe' }),
              'Argentina',
            ),
            '\n        ',
          ),
          '\n      ',
        ),
        '\n      ',
        element('div', { class: 'mp-error-msg', tabindex: '-1' }, 'Choose a country'),
        '\n      ',
        element(
          'button',
          {
            id: 'mp-button-country',
            type: 'button',
            'data-next': '#country-next-panel',
            'data-success': 'Selected {{country}}',
          },
          'Continue country',
        ),
        '\n    ',
      ),
    ),
    element('div', { id: 'country-next-panel', class: 'mp-hidden' }, 'Next panel'),
    '\n    ',
    element('button', { id: 'mp-credentials-change-country', type: 'button' }, 'Change country'),
    '\n    ',
    element('button', { id: 'mp-integration-auth-login', type: 'button' }, 'Link account'),
    '\n    ',
    element('button', { id: 'mp-integration-auth-login-update', type: 'button' }, 'Update link'),
    '\n    ',
    element('button', { id: 'mp-integration-auth-failed', type: 'button' }, 'Try again'),
    '\n    ',
    element(
      'div',
      { id: 'supportModal', style: 'display:none' },
      '\n      ',
      logCount
        ? fragment(
            element(
              'table',
              {},
              element(
                'thead',
                {},
                element(
                  'tr',
                  {},
                  element('th', {}, element('input', { type: 'checkbox', id: 'selectAllCheckbox' })),
                ),
              ),
              element(
                'tbody',
                {},
                '\n      ',
                Array.from({ length: logCount }, (_, index) =>
                  element(
                    'tr',
                    {},
                    element(
                      'td',
                      {},
                      element('input', {
                        type: 'checkbox',
                        name: 'selected_files[]',
                        value: `fixture-${index}.log`,
                      }),
                    ),
                  ),
                ),
                '\n      ',
              ),
            ),
            element('button', { id: 'downloadSelected', type: 'button', disabled: true }, 'Download'),
            element('div', { id: 'mp-pagination' }),
          )
        : element('p', {}, 'No logs'),
      '\n    ',
    ),
    '\n  ',
  );
}

module.exports = { element, fragment, settingsMarkup };
