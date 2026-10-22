const { createAdminRuntime: createRuntime } = require('./helpers/admin-runtime');
const { settingsMarkup, element } = require('./fixtures/settings-markup');

const functions = [
  'clearMessage', 'selectTestMode', 'mpVerifyAlertTestMode', 'mpShowSnackbar',
  'mpValidateStoreTips', 'mpValidatePaymentTips', 'mpGoToNextStep', 'mpContinueToNextStep',
  'mpGetRequirements', 'mpGetPaymentMethods', 'mpSettingsAccordionStart', 'mpSettingsAccordionOptions',
  'mpUpdateOptionCredentials', 'mpUpdateTestCredentials', 'hideCredentialsErrors', 'showCredentialsError',
  'mpUpdateStoreInformation', 'mpUpdateTestMode', 'mpSwitchAccount', 'waitForMPDeviceSessionID',
  'mpRedirectSellerToIntegrationAuthLogin', 'mpIntegrationAuthLogin', 'mpIntegrationAuthFailed',
  'mpCredentialsInputListeners', 'mpCredentialsModalButtonsListeners', 'switchVisibility',
  'mpSelectCredentialsCountry', 'mpSelect', 'mp_settings_screen_load', 'openSupportModal',
  'closeSupportModal', 'mpKeepStepOneOpenOnLoad', 'mpDeactivateProdModeRadio', 'mpHasNotProdKeys',
];
const params = {
  nonce: 'synthetic-settings-nonce', show_advanced_text: 'Show advanced', hide_advanced_text: 'Hide advanced',
  show_access_token: 'Show token', hide_access_token: 'Hide token',
};

describe.each(['source', 'minified'])('Admin settings — real functions, DOM and jQuery (%s)', assetVariant => {
  const createAdminRuntime = (markup, globals) => createRuntime(markup, globals, assetVariant);
  let runtime;
  let api;
  let metrics;
  beforeEach(() => {
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2026-10-12T00:00:00Z'));
    metrics = { addStoreConfigurationsStepTimer: jest.fn(), stepPaymentMethodsCallback: jest.fn() };
    runtime = createAdminRuntime(settingsMarkup(), {
      mercadopago_settings_admin_js_params: params,
      MP_DEVICE_SESSION_ID: '',
      window: { melidata: { client: metrics } },
    });
    api = runtime.load('mp-admin-settings.js', `({${functions.join(',')}, setFingerprint(value) { MP_DEVICE_SESSION_ID = value; }})`);
  });
  afterEach(() => runtime.cleanup());
  const el = id => document.getElementById(id);
  const request = (action, index) => runtime.request(action, index);

  it.each([
    ['mp-public-key-test', true], ['mp-access-token-test', true], [null, false],
  ])('warns in test mode when the credential %s is empty', (emptyField, expected) => {
    if (emptyField) el(emptyField).value = '';
    expect(api.mpVerifyAlertTestMode()).toBe(expected);
    expect(el('mp-red-badge').parentElement.style.display).toBe(expected ? 'flex' : 'none');
    el('mp-settings-testmode-prod').checked = true;
    expect(api.mpVerifyAlertTestMode()).toBe(false);
  });

  it('switches badge, icons and helper visibility in both test and production mode', () => {
    api.selectTestMode(true);
    expect(el('mp-mode-badge')).toHaveClass('mp-settings-test-mode-alert');
    expect(el('mp-orange-badge')).toHaveClass('mp-settings-alert-payment-methods-orange');
    expect(el('mp-icon-badge')).toHaveClass('mp-settings-icon-warning');
    expect(el('mp-helper-test').style.display).toBe('block');
    expect(el('mp-helper-prod').style.display).toBe('none');
    api.selectTestMode(false);
    expect(el('mp-mode-badge')).toHaveClass('mp-settings-prod-mode-alert');
    expect(el('mp-icon-badge')).toHaveClass('mp-settings-icon-success');
    expect(el('mp-mode-badge-prod').style.display).toBe('block');
    expect(el('mp-title-helper-test').style.display).toBe('none');
    expect(el('mp-red-badge').parentElement.style.display).toBe('none');
  });

  it('appends and expires a real snackbar after five seconds', () => {
    api.mpShowSnackbar('Saved successfully', 'success');
    expect(document.querySelector('.mp-snackbar-success').textContent.trim()).toBe('Saved successfully');
    jest.advanceTimersByTime(4999);
    expect(document.querySelector('.mp-snackbar')).not.toBeNull();
    jest.advanceTimersByTime(1);
    expect(document.querySelector('.mp-snackbar')).toBeNull();
    document.body.appendChild(element("div", { class: "mp-alert" }, "Old message"));
    api.clearMessage();
    expect(document.querySelector('.mp-alert')).toBeNull();
  });

  it.each([
    ['mpValidateStoreTips', 'mp_validate_store_tips', 'mp-settings-icon-store'],
    ['mpValidatePaymentTips', 'mp_validate_payment_tips', 'mp-settings-icon-payment'],
  ])('sends the nonce and updates the real icon for %s success and failure', (method, action, id) => {
    api[method]();
    expect(request(action).payload).toEqual({ action, nonce: 'synthetic-settings-nonce' });
    request(action).deferred.resolve({ success: true });
    expect(el(id)).toHaveClass('mp-settings-icon-success');
    api[method]();
    request(action, 1).deferred.resolve({ success: false });
    expect(el(id)).not.toHaveClass('mp-settings-icon-success');
    el(id).classList.add('mp-settings-icon-success');
    api[method]();
    request(action, 2).deferred.reject({ responseJSON: { data: 'Unavailable' } });
    expect(el(id)).not.toHaveClass('mp-settings-icon-success');
  });

  it('reflects server requirements without substituting the response callback', () => {
    api.mpGetRequirements();
    expect(request('mp_get_requirements').payload).toEqual({ action: 'mp_get_requirements', nonce: 'synthetic-settings-nonce' });
    request('mp_get_requirements').deferred.resolve({ data: { ssl: true, gd_ext: false } });
    expect(el('mp-req-ssl')).toHaveClass('mp-settings-icon-success');
    expect(el('mp-req-gd')).toHaveClass('mp-settings-icon-warning');
    expect(el('mp-req-gd')).not.toHaveClass('mp-settings-icon-success');
    expect(el('mp-req-ssl').style.display).toBe('');
  });

  const gateways = () => ['Card', 'Pix'].map((name, index) => ({
    enabled: index ? 'no' : 'yes', title_gateway: name, description: `${name} description`,
    link: `/settings/${name.toLowerCase()}`, icon: `/${name.toLowerCase()}.svg`,
    badge_translator: { yes: 'Active', no: 'Inactive' },
  }));

  it('replaces stale payment methods and preserves the server order and active badges', () => {
    api.mpGetPaymentMethods();
    expect(request('mp_get_payment_methods').payload.nonce).toBe('synthetic-settings-nonce');
    request('mp_get_payment_methods').deferred.resolve({ data: gateways() });
    expect([...document.querySelectorAll('.mp-settings-payment-block b')].map(node => node.textContent)).toEqual(['Card', 'Pix']);
    expect(document.querySelector('.mp-settings-badge-active').textContent).toBe('Active');
    expect(document.querySelector('.mp-settings-badge-inactive').textContent).toBe('Inactive');
    expect(document.querySelector('a.mp-settings-link').getAttribute('href')).toBe('/settings/card');
    expect(metrics.stepPaymentMethodsCallback).toHaveBeenCalledTimes(1);
  });

  it('renders payment methods when external analytics is unavailable', () => {
    runtime.window.melidata = undefined;
    api.mpGetPaymentMethods();
    request('mp_get_payment_methods').deferred.resolve({ data: gateways() });
    expect(document.querySelectorAll('.mp-settings-payment-block')).toHaveLength(2);
  });

  it.each([
    ['mp-step-2', 'mp-store-info-arrow-up', { step: 'business' }],
    ['mp-step-3', 'mp-payments-arrow-up', { step: 'payment_methods', sendOnClose: true }],
    ['mp-step-4', 'mp-modes-arrow-up', { step: 'mode' }],
  ])('shows %s, hides the previous step and records the proper external timer', (step, arrow, expected) => {
    api.mpGoToNextStep('mp-step-1', step, 'mp-credentials-arrow-up', arrow);
    expect(el('mp-step-1').style.display).toBe('none');
    expect(el(step).style.display).toBe('block');
    expect(el(arrow)).toHaveClass('mp-arrow-up');
    expect(el('mp-credentials-arrow-up')).not.toHaveClass('mp-arrow-up');
    expect(metrics.addStoreConfigurationsStepTimer).toHaveBeenCalledWith(expected);
  });

  it('moves between steps without requiring analytics and handles an untracked step', () => {
    api.mpGoToNextStep('mp-step-2', 'mp-step-1', 'mp-store-info-arrow-up', 'mp-credentials-arrow-up');
    expect(metrics.addStoreConfigurationsStepTimer).not.toHaveBeenCalled();
    runtime.window.melidata = undefined;
    api.mpGoToNextStep('mp-step-1', 'mp-step-2', 'mp-credentials-arrow-up', 'mp-store-info-arrow-up');
    expect(el('mp-step-2').style.display).toBe('block');
  });

  it('registers the continue and delayed credentials buttons with real step transitions', () => {
    api.mpContinueToNextStep();
    el('mp-payment-method-continue').click();
    expect(el('mp-step-4').style.display).toBe('block');
    api.mpUpdateOptionCredentials();
    api.mpKeepStepOneOpenOnLoad();
    el('mp-btn-credentials').click();
    expect(el('mp-step-1').style.display).not.toBe('none');
    jest.advanceTimersByTime(2000);
    expect(el('mp-step-1').style.display).toBe('none');
    expect(el('mp-step-2').style.display).toBe('block');
  });

  it('opens/closes both accordions and updates the advanced-options text', () => {
    api.mpSettingsAccordionStart();
    document.querySelector('.mp-settings-title-align').click();
    expect(el('mp-step-1').style.display).toBe('block');
    expect(el('mp-credentials-arrow-up')).toHaveClass('mp-arrow-up');
    document.querySelector('.mp-settings-title-align').click();
    expect(el('mp-step-1').style.display).toBe('none');
    api.mpSettingsAccordionOptions();
    el('mp-advanced-options').click();
    expect(el('mp-advanced-options').textContent).toBe('Hide advanced');
    expect(el('mp-advanced-options').nextElementSibling.style.display).toBe('block');
    el('mp-advanced-options').click();
    expect(el('mp-advanced-options').textContent).toBe('Show advanced');
    expect(el('block-two')).toHaveClass('mp-settings-flex-start');
  });

  it('submits actual test credential values and nonce, closes modal and refreshes methods on success', () => {
    el('msg-info-credentials').replaceChildren(element("div", { class: "mp-card-info" }, "Previous message"));
    api.showCredentialsError('public-key', 'Old error');
    api.showCredentialsError('access-token', 'Old error');
    api.mpUpdateTestCredentials();
    el('mp-btn-update-test-credentials').click();
    expect(request('mp_update_option_credentials').payload).toEqual({ public_key_test: 'synthetic-public-key', access_token_test: 'synthetic-access-token', action: 'mp_update_option_credentials', nonce: 'synthetic-settings-nonce' });
    expect(document.querySelector('.mp-card-info')).toBeNull();
    expect(document.querySelector('.mp-modal-alert')).toBeNull();
    request('mp_update_option_credentials').deferred.resolve({ success: true, data: 'Credentials saved' });
    expect(el('mp-credentials-modal').style.display).toBe('none');
    expect(document.querySelector('.mp-snackbar-success').textContent).toContain('Credentials saved');
    expect(request('mp_get_payment_methods').payload.action).toBe('mp_get_payment_methods');
  });

  it('marks both credential inputs on business rejection and clears errors through actual change listeners', () => {
    api.mpUpdateTestCredentials();
    api.mpCredentialsInputListeners();
    el('mp-btn-update-test-credentials').click();
    request('mp_update_option_credentials').deferred.resolve({ success: false, data: { message: 'Invalid credentials' } });
    ['public-key', 'access-token'].forEach(field => {
      expect(el(`mp-test-${field}`).querySelector('input')).toHaveClass('mp-settings-modal-input-alert');
      expect(el(`mp-modal-alert-${field}`).querySelector('p').innerText).toBe('Invalid credentials');
      el(`mp-test-${field}`).querySelector('input').dispatchEvent(new Event('change', { bubbles: true }));
      expect(el(`mp-modal-alert-${field}`)).toBeNull();
      expect(el(`mp-test-${field}`).querySelector('label')).not.toHaveClass('mp-settings-modal-text-alert');
      expect(el(`mp-test-${field}`).querySelector('input')).not.toHaveClass('mp-settings-modal-input-alert');
    });
    expect(el('mp-credentials-modal').style.display).toBe('flex');
  });

  it('reports an HTTP error from the actual credential callback', () => {
    api.mpUpdateTestCredentials();
    el('mp-btn-update-test-credentials').click();
    request('mp_update_option_credentials').deferred.reject({ data: 'HTTP unavailable' });
    expect(document.querySelector('.mp-snackbar-error').textContent).toContain('HTTP unavailable');
    expect(el('mp-credentials-modal').style.display).toBe('flex');
  });

  it('allows optional credential listener elements to be absent', () => {
    ['mp-test-access-token', 'mp-test-public-key', 'mp-btn-update-test-credentials', 'mp-btn-credentials'].forEach(id => el(id).remove());
    expect(() => { api.mpCredentialsInputListeners(); api.mpUpdateTestCredentials(); api.mpUpdateOptionCredentials(); api.hideCredentialsErrors(null, null); }).not.toThrow();
  });

  it('submits real store field values, then validates tips and advances only after success and delay', () => {
    api.mpUpdateStoreInformation();
    el('mp-store-info-save').click();
    expect(request('mp_update_store_information').payload).toEqual({
      store_url_ipn: 'https://service.example.invalid/notify', store_url_ipn_options: 'yes', store_categories: 'retail',
      store_category_id: 'category-fixture', store_integrator_id: 'synthetic-integrator', store_identificator: 'Synthetic shop',
      store_debug_mode: 'yes', store_cron_config: 'no', action: 'mp_update_store_information', nonce: 'synthetic-settings-nonce',
    });
    request('mp_update_store_information').deferred.resolve({ success: true, data: 'Store saved' });
    expect(request('mp_validate_store_tips').payload.nonce).toBe('synthetic-settings-nonce');
    jest.advanceTimersByTime(2999);
    expect(el('mp-step-2').style.display).not.toBe('none');
    jest.advanceTimersByTime(1);
    expect(el('mp-step-2').style.display).toBe('none');
    expect(el('mp-step-3').style.display).toBe('block');
  });

  it.each(['business', 'http'])('keeps the store step open and shows feedback after %s rejection', failure => {
    el('mp-store-url-ipn-options').checked = false;
    el('mp-store-debug-mode').checked = false;
    el('mp-store-cron-config').remove();
    api.mpUpdateStoreInformation();
    el('mp-store-info-save').click();
    const pending = request('mp_update_store_information');
    expect(pending.payload.store_url_ipn_options).toBe('no');
    expect(pending.payload.store_debug_mode).toBeUndefined();
    expect(pending.payload.store_cron_config).toBeUndefined();
    if (failure === 'business') pending.deferred.resolve({ success: false, data: 'Store rejected' });
    else pending.deferred.reject({ data: 'Store rejected' });
    expect(document.querySelector('.mp-snackbar-error').textContent).toContain('Store rejected');
    expect(el('mp-step-2').style.display).not.toBe('none');
    expect(runtime.requests).toHaveLength(1);
  });

  it('reacts to actual mode radio changes and saves the selected production mode', () => {
    api.mpUpdateTestMode();
    el('mp-settings-testmode-prod').click();
    expect(el('mp-mode-badge')).toHaveClass('mp-settings-prod-mode-alert');
    el('mp-store-mode-save').click();
    expect(request('mp_update_test_mode').payload).toEqual({ input_mode_value: 'no', input_verify_alert_test_mode: 'no', action: 'mp_update_test_mode', nonce: 'synthetic-settings-nonce' });
    request('mp_update_test_mode').deferred.resolve({ success: true, data: 'Mode saved' });
    expect(document.querySelector('.mp-snackbar-success').textContent).toContain('Mode saved');
    el('mp-settings-testmode-test').click();
    expect(el('mp-mode-badge')).toHaveClass('mp-settings-test-mode-alert');
  });

  it.each(['business', 'http'])('shows mode errors from %s rejection and retains the selected test mode', failure => {
    el('mp-public-key-test').value = '';
    api.mpUpdateTestMode();
    el('mp-store-mode-save').click();
    expect(request('mp_update_test_mode').payload.input_verify_alert_test_mode).toBe('yes');
    if (failure === 'business') request('mp_update_test_mode').deferred.resolve({ success: false, data: 'Mode rejected' });
    else request('mp_update_test_mode').deferred.reject({ data: 'Mode rejected' });
    expect(document.querySelector('.mp-snackbar-error').textContent).toContain('Mode rejected');
    expect(el('mp-settings-testmode-test').checked).toBe(true);
    expect(el('mp-red-badge').parentElement.style.display).toBe('flex');
  });

  it.each(['mp-public-key-prod', 'mp-access-token-production'])('prevents production when %s is empty and switches to test mode after the server accepts it', id => {
    el(id).value = '';
    el('mp-settings-testmode-prod').checked = true;
    expect(api.mpHasNotProdKeys()).toBe(true);
    api.mpDeactivateProdModeRadio();
    request('mp_update_test_mode').deferred.resolve({ success: true });
    expect(el('mp-settings-testmode-prod').disabled).toBe(true);
    expect(el('mp-settings-testmode-prod').checked).toBe(false);
    expect(el('mp-settings-testmode-test').checked).toBe(true);
    expect(el('mp-mode-badge')).toHaveClass('mp-settings-test-mode-alert');
  });

  it('also blocks production when credential elements are not rendered and the server rejects mode update', () => {
    el('mp-public-key-prod').remove();
    el('mp-access-token-production').remove();
    expect(api.mpHasNotProdKeys()).toBe(true);
    api.mpDeactivateProdModeRadio();
    request('mp_update_test_mode').deferred.resolve({ success: false });
    expect(el('mp-settings-testmode-prod').disabled).toBe(true);
  });

  it('permits production with both real credential fields populated without posting a mode update', () => {
    el('mp-settings-testmode-prod').disabled = true;
    expect(api.mpHasNotProdKeys()).toBe(false);
    api.mpDeactivateProdModeRadio();
    expect(el('mp-settings-testmode-prod').disabled).toBe(false);
    expect(runtime.requests).toHaveLength(0);
  });

  it('opens the credential modal and switches token visibility and accessible labels in both directions', () => {
    el('mp-credentials-modal').style.display = 'none';
    api.mpCredentialsModalButtonsListeners();
    el('mp-settings-auto-data-consult').click();
    expect(el('mp-credentials-modal').style.display).toBe('flex');
    ['production', 'test'].forEach(scope => {
      el(`mp-${scope}-token-button`).click();
      expect(el(`mp-access-token-${scope}`).type).toBe('text');
      expect(el(`mp-${scope}-token-button`).ariaLabel).toBe('Hide token');
      expect(el(`mp-${scope}-token-img`)).toHaveClass('mp-access-token-button-open');
      el(`mp-${scope}-token-button`).click();
      expect(el(`mp-access-token-${scope}`).type).toBe('password');
      expect(el(`mp-${scope}-token-img`).title).toBe('Show token');
      expect(el(`mp-${scope}-token-img`)).toHaveClass('mp-access-token-button-closed');
    });
    el('mp-credentials-close-modal').click();
    expect(el('mp-credentials-modal').style.display).toBe('none');
  });

  it('registers optional modal buttons safely when all are absent', () => {
    ['mp-settings-auto-data-consult', 'mp-credentials-close-modal', 'mp-production-token-button', 'mp-test-token-button'].forEach(id => el(id).remove());
    expect(() => api.mpCredentialsModalButtonsListeners()).not.toThrow();
  });

  it('marks and focuses the country error on empty selection, then clears errors on change', () => {
    el('mp-credentials-country').value = '';
    api.mpSelectCredentialsCountry();
    el('mp-button-country').click();
    expect(document.querySelector('.mp-input-group')).toHaveClass('mp-error');
    expect(el('mp-button-country')).toHaveClass('mp-button-error');
    expect(document.activeElement).toBe(document.querySelector('.mp-error-msg'));
    expect(el('country-next-panel')).toHaveClass('mp-hidden');
    el('mp-credentials-country').value = 'MLB';
    el('mp-credentials-country').dispatchEvent(new Event('change', { bubbles: true }));
    expect(document.querySelector('.mp-input-group')).not.toHaveClass('mp-error');
    expect(el('mp-button-country')).not.toHaveClass('mp-button-error');
    expect(document.activeElement).toBe(el('mp-button-country'));
  });

  it('advances after a valid country and restores the country panel when changed', () => {
    api.mpSelectCredentialsCountry();
    el('mp-button-country').click();
    expect(el('mp-settings-credentials-country')).toHaveClass('mp-hidden');
    expect(el('country-next-panel')).not.toHaveClass('mp-hidden');
    expect(document.querySelector('.mp-snackbar-success').textContent).toContain('Selected Brasil');
    el('mp-credentials-change-country').click();
    expect(el('mp-settings-credentials-country')).not.toHaveClass('mp-hidden');
    expect(el('country-next-panel')).toHaveClass('mp-hidden');
  });

  it('opens/closes the real listbox and synchronizes country value, labels and aria state', () => {
    api.mpSelect();
    const select = el('mp-credentials-country-select');
    const button = select.querySelector('button');
    button.click();
    expect(select).toHaveAttribute('aria-expanded', 'true');
    expect(select.querySelector('ul')).not.toHaveClass('mp-hidden');
    expect(document.activeElement).toBe(select.querySelector('ul'));
    select.querySelector('[data-value="MLA"]').click();
    expect(el('mp-credentials-country').value).toBe('MLA');
    expect(button.textContent.trim()).toBe('Argentina');
    expect(select.querySelector('[data-value="MLA"]')).toHaveAttribute('aria-selected', 'true');
    expect(select.querySelector('[data-value="MLB"]')).toHaveAttribute('aria-selected', 'false');
    expect(select).toHaveAttribute('aria-expanded', 'false');
    button.click();
    document.body.click();
    expect(select.querySelector('ul')).toHaveClass('mp-hidden');
    expect(button).not.toHaveClass('mp-select-button-focus');
  });

  it('shows country selection after the real switch-account button is clicked', () => {
    api.mpSwitchAccount();
    el('mp-settings-credentials-country').classList.add('mp-hidden');
    el('mp-switch-account-btn').click();
    expect(el('mp-settings-credentials-linked')).toHaveClass('mp-hidden');
    expect(el('mp-settings-credentials-country')).not.toHaveClass('mp-hidden');
  });

  it('waits for a later fingerprint and clears its real interval on resolution', async () => {
    const result = api.waitForMPDeviceSessionID(5000);
    jest.advanceTimersByTime(100);
    api.setFingerprint('synthetic-fingerprint');
    jest.advanceTimersByTime(100);
    await expect(result).resolves.toBe('synthetic-fingerprint');
    expect(jest.getTimerCount()).toBe(0);
  });

  it('rejects after the fingerprint timeout and clears the interval', async () => {
    const result = api.waitForMPDeviceSessionID(5000);
    const rejected = expect(result).rejects.toThrow('Timeout waiting for MP_DEVICE_SESSION_ID');
    jest.advanceTimersByTime(5100);
    await rejected;
    expect(jest.getTimerCount()).toBe(0);
  });

  async function startAuth(method = 'mpRedirectSellerToIntegrationAuthLogin') {
    api.setFingerprint('synthetic-fingerprint');
    const pending = api[method]();
    await jest.advanceTimersByTimeAsync(100);
    await pending;
  }

  it('posts the actual selected country, fingerprint and nonce and navigates to the returned onboarding URL', async () => {
    await startAuth();
    expect(request('mp_integration_login').payload).toEqual({ action: 'mp_integration_login', site_id: 'MLB', device_fingerprint: 'synthetic-fingerprint', nonce: 'synthetic-settings-nonce' });
    request('mp_integration_login').deferred.resolve({ success: true, data: { onboarding_url: 'https://auth.example.invalid/onboarding' } });
    expect(runtime.navigation.href).toBe('https://auth.example.invalid/onboarding');
  });

  it.each(['business', 'http'])('redirects to onboarding error after %s failure', async failure => {
    await startAuth();
    if (failure === 'business') request('mp_integration_login').deferred.resolve({ success: false });
    else request('mp_integration_login').deferred.reject({ responseJSON: { data: 'Unavailable' } });
    expect(runtime.navigation.href).toContain('&onboarding_error=true');
  });

  it('redirects to onboarding error without an HTTP request when the fingerprint times out', async () => {
    const pending = api.mpRedirectSellerToIntegrationAuthLogin();
    await jest.advanceTimersByTimeAsync(5100);
    await pending;
    expect(runtime.requests).toHaveLength(0);
    expect(runtime.navigation.href).toContain('&onboarding_error=true');
  });

  it('disables the clicked integration button and executes real auth rather than a mock', async () => {
    api.setFingerprint('synthetic-fingerprint');
    api.mpIntegrationAuthLogin();
    el('mp-integration-auth-login').click();
    expect(el('mp-integration-auth-login').disabled).toBe(true);
    expect(el('mp-integration-auth-login')).toHaveClass('mp-disabled-button');
    await jest.advanceTimersByTimeAsync(100);
    expect(request('mp_integration_login').payload.device_fingerprint).toBe('synthetic-fingerprint');
  });

  it('runs real auth when continuing the country selector for an account switch', async () => {
    el('mp-button-country').setAttribute('data-switch-account', 'true');
    api.setFingerprint('synthetic-fingerprint');
    api.mpSelectCredentialsCountry();
    el('mp-button-country').click();
    expect(el('mp-button-country').disabled).toBe(true);
    expect(el('mp-button-country')).toHaveClass('mp-disabled-button');
    await jest.advanceTimersByTimeAsync(100);
    expect(request('mp_integration_login').payload.site_id).toBe('MLB');
  });

  it('removes onboarding_error from the URL and requests reload on retry', () => {
    runtime.navigation.href += '&onboarding_error=true&other=preserved';
    api.mpIntegrationAuthFailed();
    el('mp-integration-auth-failed').click();
    const replaced = runtime.window.history.replaceState.mock.calls[0][2];
    expect(new URL(replaced).searchParams.has('onboarding_error')).toBe(false);
    expect(new URL(replaced).searchParams.get('other')).toBe('preserved');
    expect(runtime.navigation.reload).toHaveBeenCalledTimes(1);
  });

  it('paginates real log rows and updates download availability from selection', () => {
    runtime.dispatchReady();
    const rows = [...document.querySelectorAll('input[name="selected_files[]"]')].map(node => node.closest('tr'));
    expect(rows[0].style.display).toBe('table-row');
    expect(rows[8].style.display).toBe('none');
    expect(document.querySelectorAll('.mp-page-span')).toHaveLength(2);
    document.querySelectorAll('.mp-page-span')[1].click();
    expect(rows[0].style.display).toBe('none');
    expect(rows[8].style.display).toBe('table-row');
    expect(document.querySelector('.mp-page-span.active').textContent).toBe('2');
    rows[8].querySelector('input').click();
    expect(el('downloadSelected').disabled).toBe(false);
    rows[8].querySelector('input').click();
    expect(el('downloadSelected').disabled).toBe(true);
    el('selectAllCheckbox').click();
    expect([...document.querySelectorAll('input[name="selected_files[]"]')].every(node => node.checked)).toBe(true);
    expect(el('downloadSelected').disabled).toBe(false);
    el('selectAllCheckbox').click();
    expect([...document.querySelectorAll('input[name="selected_files[]"]')].every(node => !node.checked)).toBe(true);
    expect(el('downloadSelected').disabled).toBe(true);
    api.openSupportModal();
    expect(el('supportModal').style.display).toBe('block');
    api.closeSupportModal();
    expect(el('supportModal').style.display).toBe('none');
  });

  it('initializes the full representative panel without replacing any internal function', () => {
    api.mp_settings_screen_load();
    expect(runtime.requests.map(({ payload }) => payload.action)).toEqual(['mp_get_requirements', 'mp_get_payment_methods', 'mp_validate_store_tips', 'mp_validate_payment_tips']);
    expect(el('mp-step-1').style.display).toBe('block');
    expect(el('mp-credentials-arrow-up')).toHaveClass('mp-arrow-up');
    el('mp-payment-method-continue').click();
    expect(el('mp-step-4').style.display).toBe('block');
    el('mp-settings-auto-data-consult').click();
    expect(el('mp-credentials-modal').style.display).toBe('flex');
  });
});
