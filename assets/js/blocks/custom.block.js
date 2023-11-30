/* globals wc_mercadopago_custom_blocks_params */

import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

const paymentMethodName = 'woo-mercado-pago-custom';

const settings = getSetting(`woo-mercado-pago-custom_data`, {});
const defaultLabel = decodeEntities(settings.title) || 'Checkout Custom';

const Label = (props) => {
  const { PaymentMethodLabel } = props.components;
  return <PaymentMethodLabel text={defaultLabel} />;
};

const Content = () => {
  return (
    <div>

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