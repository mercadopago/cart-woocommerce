const { resolveAlias } = require('../../../helpers/path-resolver');
const { loadFile } = require('../../../helpers/load-file');

const threeDsHandlerPath = resolveAlias('assets/js/checkouts/custom/entities/three-ds-handler.js');

describe('MPThreeDSHandler', () => {
  let MPThreeDSHandler;
  let handler;

  beforeAll(() => {
    global.wc_mercadopago_custom_checkout_params = {};
    MPThreeDSHandler = loadFile(threeDsHandlerPath, 'MPThreeDSHandler', global);
  });

  beforeEach(() => {
    jest.clearAllMocks();
    handler = new MPThreeDSHandler();
  });

  afterEach(() => {
    global.sendMetric = jest.fn();
  });

  describe('sendMetric()', () => {
    test('Given window.sendMetric is available, When sendMetric() is called, Then it forwards the arguments to the global Datadog beacon', () => {
      const beacon = jest.fn();
      window.sendMetric = beacon;

      handler.sendMetric('MP_THREE_DS_SUCCESS', '3DS iframe Closed', 'mp_custom_checkout_security_fields_client');

      expect(beacon).toHaveBeenCalledTimes(1);
      expect(beacon).toHaveBeenCalledWith('MP_THREE_DS_SUCCESS', '3DS iframe Closed', 'mp_custom_checkout_security_fields_client');
    });

    test('Given window.sendMetric is undefined, When sendMetric() is called, Then it does not throw', () => {
      window.sendMetric = undefined;

      expect(() => {
        handler.sendMetric('TEST_ACTION', 'Test label', 'test_target');
      }).not.toThrow();
    });
  });
});
