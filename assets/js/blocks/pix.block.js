
import { useEffect, useRef } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import {
  addDiscountAndCommission,
  formatCartAmount,
  removeDiscountAndCommission,
} from './helpers/cart-update.helper';

import TestMode from './components/TestMode';
import PixTemplate from './components/PixTemplate';
import TermsAndConditions from './components/TermsAndConditions';
import RowImageSelect from './components/RowImageSelect';

const targetName = "mp_checkout_blocks";
const paymentMethodName = 'woo-mercado-pago-pix';

// Smart Pix eligibility follows the BRL Super Token contract: exactly two decimals. The shared
// formatCartAmount() drops trailing zeros when it applies the currency ratio ("100", "100.5"),
// which the authenticator rejects without calling the SDK.
const toSmartPixAmount = (amount) => {
  const value = Number(amount);
  return Number.isFinite(value) ? value.toFixed(2) : amount;
};

const settings = getSetting(`woo-mercado-pago-pix_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Pix';

// WooCommerce only counts a FAIL observer response that carries a message; without one it adds
// "Something went wrong" to wc/checkout. This context belongs to no notice container, so an
// error the Smart Pix block already shows inline is handled without a second, visible notice.
const SMART_PIX_INLINE_NOTICE_CONTEXT = 'mercadopago/smart-pix-inline';

// Metrics and the error dispatcher are separate scripts that may be missing or broken in a
// store; their failure must never replace the checkout response (Error Cascade rule).
const sendBlocksMetric = (name, value) => {
  try {
    if (typeof window.sendMetric === 'function') {
      window.sendMetric(name, value, targetName);
    }
  } catch (error) {
    // Telemetry is optional.
  }
};

const dispatchBlocksCheckoutError = (checkoutResponse) => {
  try {
    window.MPCheckoutErrorDispatcher?.dispatchEventWhenBlocksCheckoutErrorOccurred?.(checkoutResponse);
  } catch (error) {
    // The checkout failure notice is still returned to WooCommerce.
  }
};

// The CDN lifecycle refresh may throw or reject; the cart update keeps going without it.
const refreshSmartPixLifecycle = (lifecycle) => {
  try {
    const refresh = lifecycle?.refresh?.();
    if (typeof refresh?.catch === 'function') {
      refresh.catch(() => {});
    }
  } catch (error) {
    // Conventional Pix stays available.
  }
};

// The lifecycle comes from the CDN bundle; a failure there must keep the plugin notice.
const resolveSmartPixCheckoutFailure = (lifecycle, paymentDetails) => {
  try {
    return lifecycle?.handleCheckoutFailure?.(paymentDetails) ?? null;
  } catch (error) {
    return null;
  }
};

const usePixCheckoutLifecycle = (props) => {
  const { eventRegistration, emitResponse } = props;
  const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail } = eventRegistration;
  const cartTotalValue = props.billing?.cartTotal?.value ?? null;
  const cartCurrency = props.billing?.currency ?? null;
  const successResponseType = emitResponse.responseTypes.SUCCESS;
  const errorResponseType = emitResponse.responseTypes.ERROR;
  const failResponseType = emitResponse.responseTypes.FAIL;
  const paymentsNoticeContext = emitResponse.noticeContexts.PAYMENTS;
  const checkoutNoticeContext = emitResponse.noticeContexts.CHECKOUT;
  const lifecycleRef = useRef(null);
  const cartTotalRef = useRef({ currency: cartCurrency, value: cartTotalValue });
  const previousCartTotalRef = useRef(cartTotalValue);

  cartTotalRef.current = { currency: cartCurrency, value: cartTotalValue };

  useEffect(() => {
    addDiscountAndCommission(extensionCartUpdate, paymentMethodName);

    let lifecycle = null;
    let unsubscribe = null;

    const registerConventional = () => {
      unsubscribe = onPaymentSetup(() => ({
        type: successResponseType,
        meta: {
          paymentMethodData: {...window.mpHiddenInputDataFromBlocksCheckout},
        },
      }));
    };

    const mountSmartPix = () => {
      if (lifecycle) return;
      const createLifecycle = window.mpSmartPixBlocksLifecycle?.create;
      if (typeof createLifecycle !== 'function') return;
      try {
        lifecycle = createLifecycle({
          emitResponse: {
            responseTypes: {
              SUCCESS: successResponseType,
              ERROR: errorResponseType,
            },
            noticeContexts: {
              CHECKOUT: checkoutNoticeContext,
            },
          },
          getConventionalPaymentMethodData: () => ({
            ...window.mpHiddenInputDataFromBlocksCheckout,
          }),
          getCartAmount: () => toSmartPixAmount(formatCartAmount(
            cartTotalRef.current.value,
            cartTotalRef.current.currency,
            settings.params.currencyRatio,
          )),
          onPaymentSetup,
        });

        if (
          typeof lifecycle?.mount !== 'function'
          || typeof lifecycle?.destroy !== 'function'
          || typeof lifecycle?.refresh !== 'function'
        ) {
          throw new TypeError('invalid_smart_pix_blocks_lifecycle');
        }

        unsubscribe?.();
        unsubscribe = null;
        lifecycle.mount();
        lifecycleRef.current = lifecycle;
      } catch {
        try { lifecycle?.destroy?.(); } catch { /* Pix cleanup must not break the host. */ }
        lifecycle = null;
        try {
          if (typeof window.sendMetric === 'function') {
            window.sendMetric(
              'MP_PIX_BLOCKS_LIFECYCLE_CREATION_ERROR',
              'lifecycle_creation_failed',
              'mp_pix_blocks_init_error',
            );
          }
        } catch { /* A diagnostic failure must not interfere with conventional Pix. */ }
      }
      if (!lifecycle && !unsubscribe) registerConventional();
    };

    document.addEventListener('mp_smart_pix_bundle_ready', mountSmartPix);
    mountSmartPix();
    if (!lifecycle && !unsubscribe) registerConventional();

    return () => {
      document.removeEventListener('mp_smart_pix_bundle_ready', mountSmartPix);
      removeDiscountAndCommission(extensionCartUpdate, paymentMethodName);
      if (lifecycle) {
        if (lifecycleRef.current === lifecycle) {
          lifecycleRef.current = null;
        }
        try { lifecycle.destroy(); } catch { /* Keep React teardown independent of Pix. */ }
        return;
      }

      unsubscribe?.();
    };
  }, [checkoutNoticeContext, errorResponseType, onPaymentSetup, successResponseType]);

  useEffect(() => {
    if (Object.is(previousCartTotalRef.current, cartTotalValue)) {
      return;
    }

    previousCartTotalRef.current = cartTotalValue;
    refreshSmartPixLifecycle(lifecycleRef.current);
  }, [cartTotalValue]);


  useEffect(() => {

    const unsubscribe = onCheckoutSuccess(async (checkoutResponse) => {
      checkoutResponse.processingResponse.message = paymentMethodName;
      const processingResponse = checkoutResponse.processingResponse;
      sendBlocksMetric("MP_PIX_BLOCKS_SUCCESS", processingResponse.paymentStatus);
      return { type: successResponseType };
    });

    return () => unsubscribe();
  }, [onCheckoutSuccess, successResponseType]);

  useEffect(() => {
    const unsubscribe = onCheckoutFail(checkoutResponse => {
      dispatchBlocksCheckoutError(checkoutResponse);

      const processingResponse = checkoutResponse?.processingResponse;
      const paymentDetails = processingResponse?.paymentDetails;
      sendBlocksMetric("MP_PIX_BLOCKS_ERROR", processingResponse?.paymentStatus);

      const smartPixFailure = resolveSmartPixCheckoutFailure(lifecycleRef.current, paymentDetails);
      if (smartPixFailure?.placement === 'inline') {
        return {
          type: failResponseType,
          messageContext: SMART_PIX_INLINE_NOTICE_CONTEXT,
          message: paymentDetails?.message || paymentDetails?.smart_pix_error,
        };
      }

      return {
        type: failResponseType,
        messageContext: smartPixFailure?.placement === 'checkout'
          ? checkoutNoticeContext
          : paymentsNoticeContext,
        message: paymentDetails?.message,
      };
    });

    return () => unsubscribe();
  }, [checkoutNoticeContext, failResponseType, onCheckoutFail, paymentsNoticeContext]);

};

const Label = () => {
  const feeTitle = decodeEntities(settings?.params?.fee_title || '');
  const text = `${defaultLabel} ${feeTitle}`;

  return (
    <RowImageSelect
      text={text}
      imgSrc={settings.params.icon}/>
  );
};

const Content = (props) => {
  usePixCheckoutLifecycle(props);

  const {
    test_mode_title,
    test_mode_description,
    pix_template_title,
    pix_template_subtitle,
    pix_template_src,
    pix_template_alt,
    terms_and_conditions_description,
    terms_and_conditions_link_text,
    terms_and_conditions_link_src,
    test_mode,
    amount,
    message_error_amount,
    smart_pix_mp_icon_src,
    smart_pix_pix_icon_src,
    smart_pix_bb_icon_src,
    smart_pix_nubank_icon_src,
    smart_pix_selected_account_text,
  } = settings.params;

  if (amount == null) {
    return (<><p className={'alert-message'}>{message_error_amount}</p></>);
  }

  return (
    <div className="mp-checkout-container">
      <div id="mp-checkout-pix-root" className="mp-checkout-pix-container" data-mp-smart-pix-bootstrap="pending">
        <div data-mp-smart-pix-initial-loader hidden aria-hidden="true">
          <svg viewBox="0 0 50 50" focusable="false">
            <circle cx="25" cy="25" r="20" fill="none" strokeWidth="3" />
          </svg>
        </div>
        <div className="mp-checkout-pix-content">
          {test_mode ? <TestMode title={test_mode_title} description={test_mode_description} /> : null}

          <PixTemplate
            title={pix_template_title}
            subtitle={pix_template_subtitle}
            alt={pix_template_alt}
            linkSrc={pix_template_src}
          />
        </div>
        <TermsAndConditions
          description={terms_and_conditions_description}
          linkText={terms_and_conditions_link_text}
          linkSrc={terms_and_conditions_link_src}
          checkoutClass={'pix'}
        />
        <div
          id="mp-smart-pix-blocks-root"
          className="mp-smart-pix"
          data-smart-pix-surface="blocks"
          data-smart-pix-state="unavailable"
          data-smart-pix-mp-icon-src={smart_pix_mp_icon_src}
          data-smart-pix-pix-icon-src={smart_pix_pix_icon_src}
          data-smart-pix-banco-do-brasil-icon-src={smart_pix_bb_icon_src}
          data-smart-pix-nubank-icon-src={smart_pix_nubank_icon_src}
          data-smart-pix-selected-account-copy={smart_pix_selected_account_text}
          aria-busy="false"
          hidden
        />
      </div>
    </div>
  );
};

const mercadopagoPaymentMethod = {
  name: paymentMethodName,
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: defaultLabel,
  supports: {
    features: settings?.supports ?? [],
  },
};

registerPaymentMethod(mercadopagoPaymentMethod);
