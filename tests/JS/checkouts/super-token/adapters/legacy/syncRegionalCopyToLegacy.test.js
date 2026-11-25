const { resolveRegionalCopy } = require('@super-token/adapters/platform/resolveRegionalCopy');
const { syncRegionalCopyToLegacy } = require('@super-token/adapters/legacy/syncRegionalCopyToLegacy');

afterEach(() => document.body.replaceChildren());

test.each([
  ['Account Money', 'Account Money in Mercado Pago'],
  ['Account Money in Mercado Pago', 'Account Money'],
  ['Dinero disponible en Mercado\u00a0Pago', undefined],
])('MLU translates the full rendered balance name with params %p and controller %p', (parameterCopy, controllerCopy) => {
  document.body.innerHTML = `<div id="mp-checkout-super-token-root">
    <article data-type="account_money" aria-label="Account Money in Mercado Pago" data-base-aria-label="Account Money in Mercado Pago">
      <span class="mp-super-token-payment-method__title">Account Money in Mercado Pago</span>
      <input value="123" style="color: red">
    </article></div>`;
  const params = { site_id: 'MLU', account_money_text: parameterCopy };
  const localized = resolveRegionalCopy(params, 'es-UY', 'es-UY');
  const selection = { id: 'saved-method' };
  const controller = { ACCOUNT_MONEY_TEXT: controllerCopy, selectedPaymentMethod: selection };
  const row = document.querySelector('article');
  const input = row.querySelector('input');
  input.focus();

  [1, 2].forEach(() => syncRegionalCopyToLegacy(params, localized, controller, 'MLU'));

  expect(row.querySelector('span').textContent).toBe(localized.account_money_text);
  expect(row.getAttribute('aria-label')).toBe(localized.account_money_text);
  expect(row.dataset.baseAriaLabel).toBe(localized.account_money_text);
  expect(controller.ACCOUNT_MONEY_TEXT).toBe(localized.account_money_text);
  expect(controller.selectedPaymentMethod).toBe(selection);
  expect(row.querySelector('input')).toBe(input);
  expect(input.value).toBe('123');
  expect(input.getAttribute('style')).toBe('color: red');
  expect(document.activeElement).toBe(input);
});

test('MLU updates cached copy and ARIA while preserving controls, focus and Custom content', () => {
  const site = 'MLU';
  document.body.innerHTML = `
    <div id="wallet-banner">Pagá en un toque</div>
    <div id="mp-checkout-super-token-root">
      <section class="mp-super-token-block" aria-label="Además, puedes utilizar">
        <span class="mp-super-token-block__title">Además, puedes utilizar</span>
      </section>
      <article data-type="credit_card"><label for="mp-super-token-installments-select-1">Cuotas</label>
        <select id="mp-super-token-installments-select-1"><option value="3" selected>3x 50 sin interés</option></select>
        <input id="saved-cvv" value="123" style="color: red">
        <div class="mp-installments-bank-interest-hint">*old hint</div>
      </article>
      <article data-type="account_money" aria-label="Account Money in Mercado Pago" data-base-aria-label="Account Money in Mercado Pago">
        <span class="mp-super-token-payment-method__title"><i aria-hidden="true"></i>Account Money in Mercado Pago</span>
      </article>
      <div class="custom-form"><label for="form-checkout__installments">Cuotas</label>
        <select id="form-checkout__installments"><option selected>Custom field</option></select>
        <div class="mp-installments-bank-interest-hint">Custom hint</div>
      </div>
      <footer id="mp-super-token-privacy-policy-footer"><span>Conoce&nbsp;<a href="https://www.mercadopago.com.ar/privacidad">cómo cuidamos tu privacidad</a>.</span></footer>
    </div>`;
  const locale = 'es-UY';
  // The newest params can already be correct while earlier controller/view/DOM copy is stale.
  const original = resolveRegionalCopy({ site_id: site, store_language_copy: true }, locale, locale);
  const selection = { card: 'same-card', installments: 3 };
  const viewCopy = { mpMethodsTitle: 'Además, puedes utilizar' };
  const controller = {
    selectedPaymentMethod: selection,
    MP_METHODS_TITLE: 'Además, puedes utilizar',
    variantView: { savedCardsView: { deps: { copy: viewCopy } } },
    authorizePayment: jest.fn(),
  };
  const cvv = document.getElementById('saved-cvv');
  const select = document.getElementById('mp-super-token-installments-select-1');
  const icon = document.querySelector('i');
  const onChange = jest.fn();
  const customContent = document.querySelector('.custom-form').textContent;
  select.addEventListener('change', onChange);
  cvv.focus();

  [1, 2].forEach(() => syncRegionalCopyToLegacy(original, original, controller, site));

  expect(controller.selectedPaymentMethod).toBe(selection);
  expect(controller.authorizePayment).not.toHaveBeenCalled();
  expect(controller.variantView.savedCardsView.deps.copy).toBe(viewCopy);
  expect(controller.MP_METHODS_TITLE).toBe('Además, podés utilizar');
  expect(viewCopy.mpMethodsTitle).toBe(controller.MP_METHODS_TITLE);
  expect(document.querySelector('.mp-super-token-block__title').textContent).toBe(controller.MP_METHODS_TITLE);
  expect(document.querySelector('.mp-super-token-block').getAttribute('aria-label')).toBe(controller.MP_METHODS_TITLE);
  expect(document.querySelector('[data-type="account_money"]').dataset.baseAriaLabel).toBe('Dinero disponible en Mercado\u00a0Pago');
  expect(document.querySelector('i')).toBe(icon);
  expect(document.getElementById('saved-cvv')).toBe(cvv);
  expect(cvv.value).toBe('123');
  expect(cvv.getAttribute('style')).toBe('color: red');
  expect(document.activeElement).toBe(cvv);
  expect(document.getElementById('mp-super-token-installments-select-1')).toBe(select);
  expect(select.value).toBe('3');
  expect(select.selectedOptions[0].textContent).toBe('3x 50 sin interés');
  expect(onChange).not.toHaveBeenCalled();
  expect(document.querySelector('.custom-form').textContent).toBe(customContent);
  expect(document.getElementById('wallet-banner').textContent).toBe('Pagá en un toque');
  expect(document.querySelector('#mp-super-token-privacy-policy-footer a').href).toBe('https://www.mercadopago.com.uy/privacidad');
});

test('repeated synchronization preserves custom copy that contains a known legacy phrase', () => {
  document.body.innerHTML = '<div id="mp-checkout-super-token-root"><span class="mp-super-token-block__title">Además, puedes utilizar crédito de la tienda</span></div>';
  const params = resolveRegionalCopy({ site_id: 'MLU', mp_methods_title: 'Además, puedes utilizar crédito de la tienda' }, 'es-UY');
  const controller = { MP_METHODS_TITLE: 'Además, puedes utilizar' };
  [1, 2].forEach(() => syncRegionalCopyToLegacy(params, params, controller, 'MLU'));
  expect(document.querySelector('span').textContent).toBe(params.mp_methods_title);
});

test.each([
  ['prepaid_card', 'Tarjeta Prepago Mercado Pago', '', 'Tarjeta Prepaga Mercado Pago', ''],
  ['credit_card', 'Visa', ' Hasta 3 meses sin intereses', 'Visa', ' Hasta 3 cuotas sin interés'],
])('%s keeps visible and accessible copy aligned after repeated synchronization', (type, name, benefit, expectedName, expectedBenefit) => {
  const oldLabel = `${name} terminada en 1234${benefit}`;
  document.body.innerHTML = `<div id="mp-checkout-super-token-root">
    <article data-type="${type}" aria-label="${oldLabel}" data-base-aria-label="${oldLabel}">
      <span class="mp-super-token-payment-method__title">${name}</span>
      <span class="mp-super-token-payment-method__value-prop" aria-hidden="true">${benefit}</span>
    </article></div>`;
  const params = resolveRegionalCopy({ site_id: 'MLU' }, 'es-UY', 'es-UY');
  const row = document.querySelector('article');

  [1, 2].forEach(() => syncRegionalCopyToLegacy(params, params, {}, 'MLU'));

  expect(document.querySelector('article')).toBe(row);
  expect(row.querySelector('.mp-super-token-payment-method__title').textContent).toBe(expectedName);
  expect(row.querySelector('.mp-super-token-payment-method__value-prop').textContent).toBe(expectedBenefit);
  expect(row.getAttribute('aria-label')).toBe(`${expectedName} terminada en 1234${expectedBenefit}`);
  expect(row.dataset.baseAriaLabel).toBe(`${expectedName} terminada en 1234${expectedBenefit}`);
});

test('MPE keeps its Peruvian privacy link when it updates an already rendered footer', () => {
  document.body.innerHTML = `<div id="mp-checkout-super-token-root">
    <footer id="mp-super-token-privacy-policy-footer"><span>Conoce&nbsp;<a href="https://www.mercadopago.com.ar/privacidad">cómo cuidamos tu privacidad</a>.</span></footer>
  </div>`;
  const params = resolveRegionalCopy({ site_id: 'MPE', store_language_copy: true }, 'es-PE', 'es-PE');

  [1, 2].forEach(() => syncRegionalCopyToLegacy(params, params, {}, 'MPE'));

  const footer = document.querySelector('#mp-super-token-privacy-policy-footer');
  expect(footer.querySelector('a').href).toBe('https://www.mercadopago.com.pe/privacidad');
  expect(footer.textContent.replace(/ /g, ' ')).toBe('Conoce cómo cuidamos tu privacidad.');
});
