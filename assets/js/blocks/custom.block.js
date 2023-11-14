/* globals wc_mercadopago_custom_blocks_params */

import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const paymentMethodName = 'woo-mercado-pago-custom';
const paymentMethodParams = wc_mercadopago_custom_blocks_params;

const settings = getSetting(`woo-mercado-pago-custom_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Custom';

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={defaultLabel} />;
};

const Content = () => {
  return (
    <test-mode
      title={paymentMethodParams.test_mode_title}
      description={paymentMethodParams.test_mode_description}
      link-text={paymentMethodParams.test_mode_link_text}
      link-src={paymentMethodParams.test_mode_link_src}
    />
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