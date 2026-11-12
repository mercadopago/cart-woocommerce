describe('Super Token bootstrap independent of Smart Pix distribution', () => {
  afterEach(() => {
    delete window.wc_mercadopago_smart_pix_params;
    jest.restoreAllMocks();
  });

  it.each([
    [{ site_id: 'MLB', standalone_bridge: 'enabled' }, true],
    [undefined, true],
    [{ site_id: 'MLA', standalone_bridge: 'enabled' }, true],
  ])('does not alter card resilience based on the Pix context %j', (params, expected) => {
    jest.resetModules();
    delete window.wc_mercadopago_supertoken_bundle_params;
    window.wc_mercadopago_smart_pix_params = params;
    const resilience = require('@super-token/composition/initializationResilience');
    const start = jest.spyOn(resilience, 'startInitializationResilience').mockImplementation(() => {});
    require('@super-token/bootstrap');
    expect(start).toHaveBeenCalledTimes(expected ? 1 : 0);
    expect(typeof window.mpFastPaymentsGetSmartTransferPaymentMethods).toBe('function');
  });
});
