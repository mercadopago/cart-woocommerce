/* globals wc_mercadopago_basic_blocks_params */

import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const paymentMethodName = 'woo-mercado-pago-basic';
const paymentMethodParams = wc_mercadopago_basic_blocks_params;

const settings = getSetting(`woo-mercado-pago-basic_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Pro';

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