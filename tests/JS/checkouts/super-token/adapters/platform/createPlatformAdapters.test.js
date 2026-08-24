const mockMpSdkAdapter = jest.fn();
const mockCoreMonitorMetricsAdapter = jest.fn();
const mockWooDomAdapter = jest.fn();
const mockVariantConfigAdapter = jest.fn();

jest.mock('@super-token/adapters/platform/MpSdkAdapter', () => ({
  MpSdkAdapter: function (...args) { mockMpSdkAdapter(...args); this.kind = 'sdk'; },
}));
jest.mock('@super-token/adapters/platform/CoreMonitorMetricsAdapter', () => ({
  CoreMonitorMetricsAdapter: function (...args) { mockCoreMonitorMetricsAdapter(...args); this.kind = 'metrics'; },
}));
jest.mock('@super-token/adapters/platform/WooDomAdapter', () => ({
  WooDomAdapter: function (...args) { mockWooDomAdapter(...args); this.kind = 'dom'; },
}));
jest.mock('@super-token/adapters/platform/VariantConfigAdapter', () => ({
  VariantConfigAdapter: function (...args) { mockVariantConfigAdapter(...args); this.kind = 'variant'; },
}));

const { createPlatformAdapters } = require('@super-token/adapters/platform/createPlatformAdapters');

describe('createPlatformAdapters', () => {
  const sdk = { id: 'sdk-instance' };

  beforeEach(() => {
    jest.clearAllMocks();
    delete window.mpSdkInstance;
    delete window.wc_mercadopago_supertoken_bundle_params;
    delete window.wc_mercadopago_woocommerce_scripts_params;
  });

  it('Given no SDK option and no window.mpSdkInstance, When called, Then it throws a fail-visible error', () => {
    expect(() => createPlatformAdapters()).toThrow(/MP JS SDK instance unavailable/);
  });

  it('Given an injected SDK, When called, Then it returns the four platform adapters', () => {
    const adapters = createPlatformAdapters({ sdk });
    expect(adapters.paymentSdk.kind).toBe('sdk');
    expect(adapters.metrics.kind).toBe('metrics');
    expect(adapters.dom.kind).toBe('dom');
    expect(adapters.variantConfig.kind).toBe('variant');
  });

  it('Given an injected SDK, When called, Then MpSdkAdapter and the metrics adapter are constructed with it', () => {
    const params = { plugin_version: '8.9.1', platform_version: '9', site_id: 'MLB', cust_id: '1', location: '/x', platform_id: 'p' };
    createPlatformAdapters({ sdk, superTokenJsVersion: '2.1.0', params });
    expect(mockMpSdkAdapter).toHaveBeenCalledWith(sdk);
    expect(mockCoreMonitorMetricsAdapter).toHaveBeenCalledWith(sdk, '2.1.0', params);
  });

  it('Given no SDK option but window.mpSdkInstance is present, When called, Then it uses the global SDK', () => {
    window.mpSdkInstance = sdk;
    createPlatformAdapters();
    expect(mockMpSdkAdapter).toHaveBeenCalledWith(sdk);
  });

  it('Given no js version and no params anywhere, When called, Then the metrics adapter gets a null js version and the empty-string params fallback (fail-visible)', () => {
    createPlatformAdapters({ sdk });
    const [, jsVersion, params] = mockCoreMonitorMetricsAdapter.mock.calls[0];
    expect(jsVersion).toBeNull();
    expect(params).toEqual({ plugin_version: '', platform_version: '', site_id: '', cust_id: '', location: '', platform_id: '' });
  });

  it('Given scripts params, When called, Then the variant config adapter is built with them', () => {
    const scriptsParams = { ab_variant: 'v2.1' };
    createPlatformAdapters({ sdk, scriptsParams });
    expect(mockVariantConfigAdapter).toHaveBeenCalledWith(scriptsParams);
  });
});
