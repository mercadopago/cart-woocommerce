const React = require('react');
const { createRoot } = require('react-dom/client');
const { act } = require('react-dom/test-utils');

const settings = {
    title: 'Pix',
    params: { amount: 10, currencyRatio: 2, icon: 'pix.png' },
};
let paymentMethod;

beforeAll(() => {
    window.React = React;
    window.wp = {
        element: React,
        htmlEntities: { decodeEntities: (value) => value },
    };
    window.wc = {
        wcBlocksRegistry: {
            registerPaymentMethod: (method) => { paymentMethod = method; },
        },
        blocksCheckout: { extensionCartUpdate: jest.fn() },
        wcSettings: { getSetting: () => settings },
    };
    window.IS_REACT_ACT_ENVIRONMENT = true;
    window.sendMetric = jest.fn();

    // Execute the distributed artifact, including its actual React effects.
    require('../../../build/pix.block.js');
});

afterAll(() => {
    delete window.mpSmartPixBlocksLifecycle;
    delete window.mpHiddenInputDataFromBlocksCheckout;
    delete window.React;
    delete window.wp;
    delete window.wc;
    delete window.IS_REACT_ACT_ENVIRONMENT;
    delete window.sendMetric;
});

// Mirrors WooCommerce's runCheckoutFailObservers (block-data/checkout/utils.ts): a FAIL or ERROR
// observer response only counts when it carries a message; with none, WooCommerce adds its generic
// notice to wc/checkout. It returns the notices that would be created.
function runCheckoutFailObservers(observerResponses) {
    const notices = [];
    let errorResponse = null;

    observerResponses.forEach((response) => {
        if (['error', 'fail'].includes(response?.type) && typeof response.message === 'string' && response.message) {
            errorResponse = response;
            notices.push({ message: response.message, context: response.messageContext });
        }
    });

    if (errorResponse === null) {
        notices.push({
            message: 'Something went wrong. Please contact us to get assistance.',
            context: 'wc/checkout',
        });
    }

    return notices;
}

function renderPix(overrides = {}) {
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root = createRoot(container);
    const subscriptions = { payment: jest.fn(), success: jest.fn(), fail: jest.fn() };
    const props = {
        billing: {
            cartTotal: { value: 1234 },
            currency: { minorUnit: 2 },
        },
        eventRegistration: {
            onPaymentSetup: jest.fn((callback) => {
                subscriptions.payment(callback);
                return jest.fn();
            }),
            onCheckoutSuccess: jest.fn((callback) => {
                subscriptions.success(callback);
                return jest.fn();
            }),
            onCheckoutFail: jest.fn((callback) => {
                subscriptions.fail(callback);
                return jest.fn();
            }),
        },
        emitResponse: {
            responseTypes: { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' },
            noticeContexts: { CHECKOUT: 'checkout', PAYMENTS: 'payments' },
        },
        ...overrides,
    };
    act(() => root.render(React.cloneElement(paymentMethod.content, props)));

    return {
        container,
        props,
        subscriptions,
        rerender: (nextProps) => act(() => root.render(
            React.cloneElement(paymentMethod.content, nextProps),
        )),
        unmount: () => {
            act(() => root.unmount());
            container.remove();
        },
    };
}

describe('Pix Block lifecycle contract', () => {
    it('mounts a late shared-bundle capability once and removes its conventional subscription', () => {
        delete window.mpSmartPixBlocksLifecycle;
        const view = renderPix();
        expect(view.props.eventRegistration.onPaymentSetup).toHaveBeenCalledTimes(1);
        const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn() };
        const create = jest.fn(() => lifecycle);
        window.mpSmartPixBlocksLifecycle = { create };
        act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));
        act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));
        expect(create).toHaveBeenCalledTimes(1);
        expect(lifecycle.mount).toHaveBeenCalledTimes(1);
        expect(view.props.eventRegistration.onPaymentSetup.mock.results[0].value).toHaveBeenCalledTimes(1);
        view.unmount();
        expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
        act(() => document.dispatchEvent(new CustomEvent('mp_smart_pix_bundle_ready')));
        expect(create).toHaveBeenCalledTimes(1);
    });
    beforeEach(() => {
        window.mpHiddenInputDataFromBlocksCheckout = { conventional_pix: '1' };
        window.wc.blocksCheckout.extensionCartUpdate.mockClear();
    });

    afterEach(() => {
        delete window.mpSmartPixBlocksLifecycle;
        document.body.innerHTML = '';
    });

    it('mounts one Smart Pix lifecycle and destroys it on unmount', () => {
        const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn() };
        const create = jest.fn(() => lifecycle);
        window.mpSmartPixBlocksLifecycle = { create };

        const view = renderPix();
        expect(create).toHaveBeenCalledTimes(1);
        expect(lifecycle.mount).toHaveBeenCalledTimes(1);
        expect(view.props.eventRegistration.onPaymentSetup).not.toHaveBeenCalled();
        expect(view.container.querySelector('#mp-smart-pix-blocks-root')
            .dataset.smartPixSurface).toBe('blocks');
        expect(window.wc.blocksCheckout.extensionCartUpdate).toHaveBeenCalledWith({
            namespace: 'mercadopago_blocks_update_cart',
            data: { action: 'add', gateway: 'woo-mercado-pago-pix' },
        });

        view.unmount();
        expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
        expect(window.wc.blocksCheckout.extensionCartUpdate).toHaveBeenLastCalledWith({
            namespace: 'mercadopago_blocks_update_cart',
            data: { action: 'remove', gateway: 'woo-mercado-pago-pix' },
        });
    });

    it('refreshes the active lifecycle with the new cart amount without remounting', () => {
        const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn() };
        const create = jest.fn(() => lifecycle);
        window.mpSmartPixBlocksLifecycle = { create };

        const view = renderPix();
        const bridge = create.mock.calls[0][0];
        expect(bridge.getCartAmount()).toBe('24.68');
        expect(bridge.getConventionalPaymentMethodData()).toEqual({ conventional_pix: '1' });
        expect(bridge.onPaymentSetup).toBe(view.props.eventRegistration.onPaymentSetup);

        view.rerender({
            ...view.props,
            billing: { ...view.props.billing, cartTotal: { value: 2000 } },
            emitResponse: { ...view.props.emitResponse },
        });
        expect(bridge.getCartAmount()).toBe('40.00');
        expect(lifecycle.refresh).toHaveBeenCalledTimes(1);
        expect(create).toHaveBeenCalledTimes(1);
        expect(lifecycle.destroy).not.toHaveBeenCalled();
        view.unmount();
    });

    it('registers exactly one conventional observer when the capability is absent', () => {
        const view = renderPix();
        expect(view.props.eventRegistration.onPaymentSetup).toHaveBeenCalledTimes(1);
        const callback = view.subscriptions.payment.mock.calls[0][0];
        expect(callback()).toEqual({
            type: 'success',
            meta: { paymentMethodData: { conventional_pix: '1' } },
        });
        window.mpHiddenInputDataFromBlocksCheckout.conventional_pix = '2';
        expect(callback().meta.paymentMethodData).toEqual({ conventional_pix: '2' });
        view.unmount();
        expect(view.props.eventRegistration.onPaymentSetup.mock.results[0].value)
            .toHaveBeenCalledTimes(1);
    });

    it('destroys a malformed lifecycle and falls back to conventional Pix', () => {
        const lifecycle = { mount: jest.fn(), destroy: jest.fn() };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };

        const view = renderPix();
        expect(lifecycle.mount).not.toHaveBeenCalled();
        expect(lifecycle.destroy).toHaveBeenCalledTimes(1);
        expect(view.props.eventRegistration.onPaymentSetup).toHaveBeenCalledTimes(1);
        expect(view.subscriptions.payment.mock.calls[0][0]().type).toBe('success');
        view.unmount();
    });

    it('records a fixed diagnostic when lifecycle creation fails and keeps conventional Pix', () => {
        window.sendMetric.mockClear();
        window.mpSmartPixBlocksLifecycle = {
            create: jest.fn(() => { throw new Error('private SDK detail'); }),
        };

        const view = renderPix();

        expect(view.props.eventRegistration.onPaymentSetup).toHaveBeenCalledTimes(1);
        expect(window.sendMetric).toHaveBeenCalledWith(
            'MP_PIX_BLOCKS_LIFECYCLE_CREATION_ERROR',
            'lifecycle_creation_failed',
            'mp_pix_blocks_init_error',
        );
        expect(JSON.stringify(window.sendMetric.mock.calls)).not.toContain('private SDK detail');
        view.unmount();
    });

    it('hands the setup observer to Smart Pix without installing a duplicate fallback', () => {
        const rejection = { type: 'error', message: 'Selecione uma conta.' };
        const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn() };
        window.mpSmartPixBlocksLifecycle = {
            create: jest.fn(({ onPaymentSetup }) => {
                lifecycle.mount.mockImplementation(() => onPaymentSetup(() => rejection));
                return lifecycle;
            }),
        };

        const view = renderPix();
        expect(view.props.eventRegistration.onPaymentSetup).toHaveBeenCalledTimes(1);
        expect(view.subscriptions.payment.mock.calls[0][0]()).toEqual(rejection);
        view.unmount();
    });

    it('given a Smart Pix lifecycle, when it is created, then it receives the checkout notice context', () => {
        const lifecycle = { mount: jest.fn(), refresh: jest.fn(), destroy: jest.fn() };
        const create = jest.fn(() => lifecycle);
        window.mpSmartPixBlocksLifecycle = { create };

        const view = renderPix();

        expect(create.mock.calls[0][0].emitResponse.noticeContexts).toEqual({ CHECKOUT: 'checkout' });
        view.unmount();
    });

    it.each([
        [{ placement: 'inline' }, {
            type: 'fail',
            messageContext: 'mercadopago/smart-pix-inline',
            message: 'Falhou.',
        }],
        [{ placement: 'checkout' }, { type: 'fail', messageContext: 'checkout', message: 'Falhou.' }],
        [null, { type: 'fail', messageContext: 'payments', message: 'Falhou.' }],
    ])('given the lifecycle resolves %j, when the Store API checkout fails, then the plugin emits %j', (resolution, expected) => {
        const lifecycle = {
            mount: jest.fn(),
            refresh: jest.fn(),
            destroy: jest.fn(),
            handleCheckoutFailure: jest.fn(() => resolution),
        };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
        const paymentDetails = { message: 'Falhou.', smart_pix_error: 'smart_pix_rejected' };

        const view = renderPix();
        const onFail = view.subscriptions.fail.mock.calls[0][0];

        expect(onFail({ processingResponse: { paymentStatus: 'failure', paymentDetails } })).toEqual(expected);
        expect(lifecycle.handleCheckoutFailure).toHaveBeenCalledWith(paymentDetails);
        view.unmount();
    });

    it('given an inline Smart Pix rejection, when WooCommerce runs the fail observers, then no visible notice is created', () => {
        const lifecycle = {
            mount: jest.fn(),
            refresh: jest.fn(),
            destroy: jest.fn(),
            handleCheckoutFailure: jest.fn(() => ({ placement: 'inline' })),
        };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
        const view = renderPix();
        const onFail = view.subscriptions.fail.mock.calls[0][0];
        const renderedContexts = Object.values(view.props.emitResponse.noticeContexts);

        const withMessage = onFail({ processingResponse: { paymentDetails: {
            message: 'Saldo insuficiente nesta conta.',
            smart_pix_error: 'smart_pix_insufficient_balance',
        } } });
        const withoutMessage = onFail({ processingResponse: { paymentDetails: {
            smart_pix_error: 'smart_pix_insufficient_balance',
        } } });

        [withMessage, withoutMessage].forEach((response) => {
            const notices = runCheckoutFailObservers([response]);

            expect(notices).toHaveLength(1);
            expect(renderedContexts).not.toContain(notices[0].context);
            expect(notices[0].context.startsWith('wc/')).toBe(false);
        });
        expect(withoutMessage.message).toBe('smart_pix_insufficient_balance');
        view.unmount();
    });

    it('given an existing Pix, when Smart Pix fails validation, then the contextual server message remains visible', () => {
        const lifecycle = {
            mount: jest.fn(),
            refresh: jest.fn(),
            destroy: jest.fn(),
            handleCheckoutFailure: jest.fn(() => ({ placement: 'checkout' })),
        };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
        const view = renderPix();
        const message = 'Já existe um pagamento pendente para este pedido. Para trocar a forma de pagamento, entre em contato com a loja.';
        const paymentDetails = { message, smart_pix_error: 'smart_pix_processing_failed' };
        const onFail = view.subscriptions.fail.mock.calls[0][0];
        const response = onFail({ processingResponse: { paymentStatus: 'failure', paymentDetails } });

        expect(response).toEqual({ type: 'fail', messageContext: 'checkout', message });
        expect(lifecycle.handleCheckoutFailure).toHaveBeenCalledWith(paymentDetails);
        expect(runCheckoutFailObservers([response])).toEqual([{ context: 'checkout', message }]);
        view.unmount();
    });

    it('given the CDN lifecycle throws, when the Store API checkout fails, then the plugin notice is kept', () => {
        const lifecycle = {
            mount: jest.fn(),
            refresh: jest.fn(),
            destroy: jest.fn(),
            handleCheckoutFailure: jest.fn(() => { throw new Error('cdn failure'); }),
        };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };

        const view = renderPix();
        const onFail = view.subscriptions.fail.mock.calls[0][0];

        expect(onFail({ processingResponse: { paymentDetails: { message: 'Falhou.' } } })).toEqual({
            type: 'fail',
            messageContext: 'payments',
            message: 'Falhou.',
        });
        view.unmount();
    });

    it('given no processing response, when the checkout fails, then the observer does not throw', () => {
        const view = renderPix();
        const onFail = view.subscriptions.fail.mock.calls[0][0];

        expect(onFail({})).toEqual({ type: 'fail', messageContext: 'payments', message: undefined });
        view.unmount();
    });
    it('given the processing answer of a linked Smart Pix order, when the checkout fails, then its server message is shown', () => {
        const view = renderPix();
        const onFail = view.subscriptions.fail.mock.calls[0][0];
        const paymentDetails = {
            message: 'Estamos processando seu pagamento com Smart Pix.',
            smart_pix_state: 'processing',
            smart_pix_message: 'Estamos processando seu pagamento com Smart Pix.',
        };

        const response = onFail({ processingResponse: { paymentDetails } });

        expect(response).toEqual({ type: 'fail', messageContext: 'payments', message: paymentDetails.message });
        expect(runCheckoutFailObservers([response])).toEqual([
            { message: paymentDetails.message, context: 'payments' },
        ]);
        view.unmount();
    });

    it('given broken metrics and error dispatcher scripts, when the checkout fails or succeeds, then the observers still answer', () => {
        const sendMetric = window.sendMetric;
        window.sendMetric = jest.fn(() => { throw new Error('metrics down'); });
        window.MPCheckoutErrorDispatcher = {
            dispatchEventWhenBlocksCheckoutErrorOccurred: jest.fn(() => { throw new Error('dispatcher down'); }),
        };
        try {
            const view = renderPix();
            const onFail = view.subscriptions.fail.mock.calls[0][0];
            const onSuccess = view.subscriptions.success.mock.calls[0][0];

            expect(onFail({ processingResponse: { paymentDetails: { message: 'Falhou.' } } })).toEqual({
                type: 'fail', messageContext: 'payments', message: 'Falhou.',
            });
            return onSuccess({ processingResponse: { paymentStatus: 'success' } }).then((result) => {
                expect(result).toEqual({ type: 'success' });
                view.unmount();
            });
        } finally {
            window.sendMetric = sendMetric;
            delete window.MPCheckoutErrorDispatcher;
        }
    });

    it('given a lifecycle refresh that throws or rejects, when the cart amount changes, then the block keeps rendering', () => {
        const lifecycle = {
            mount: jest.fn(),
            refresh: jest.fn()
                .mockImplementationOnce(() => { throw new Error('cdn refresh failure'); })
                .mockImplementationOnce(() => Promise.reject(new Error('cdn async failure'))),
            destroy: jest.fn(),
        };
        window.mpSmartPixBlocksLifecycle = { create: () => lifecycle };
        const view = renderPix();

        for (const value of [2000, 3000]) {
            expect(() => view.rerender({
                ...view.props,
                billing: { ...view.props.billing, cartTotal: { value } },
            })).not.toThrow();
        }
        expect(lifecycle.refresh).toHaveBeenCalledTimes(2);
        expect(lifecycle.destroy).not.toHaveBeenCalled();
        view.unmount();
    });
});
