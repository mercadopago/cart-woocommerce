import { mlb } from '../../data/meli_sites';

function mlbCheckoutData(scenarioName) {
  const scenario = mlb.credit_card_scenarios?.[scenarioName];
  const card = scenario?.master;
  const form = scenario?.form;
  const user = mlb.guestUserMLB;
  const shopUrl = process.env.IOS_STORE_URL;
  const productId = Number(process.env.IOS_PRODUCT_ID || 14);

  const missing = [];
  if (!shopUrl) missing.push('IOS_STORE_URL');
  if (!card?.number) missing.push('CC_MASTER');
  if (!user?.email) missing.push('GUEST_EMAIL');
  if (!user?.document) missing.push('DOC_NUMBER_MLB');
  if (!form?.docType) missing.push('DOC_TYPE_MLB');
  if (!form?.docNumber) missing.push('DOC_NUMBER_MLB');
  if (!Number.isInteger(productId) || productId < 1) missing.push('IOS_PRODUCT_ID');

  if (missing.length) {
    throw new Error(`[iOS E2E] Missing shared checkout data in e2e/.env: ${missing.join(', ')}`);
  }

  const parsedShopUrl = new URL(shopUrl);
  if (
    parsedShopUrl.protocol !== 'https:' ||
    parsedShopUrl.hostname !== 'localhost' ||
    !parsedShopUrl.port
  ) {
    throw new Error('[iOS E2E] IOS_STORE_URL must use https://localhost:<port>.');
  }

  return {
    shopUrl: parsedShopUrl.toString().replace(/\/$/, ''),
    productId,
    user,
    card,
    form,
  };
}

export function mlbApprovedCheckoutData() {
  return mlbCheckoutData('APPROVED');
}

export function mlbRejectedCheckoutData() {
  return mlbCheckoutData('REJECTED');
}
