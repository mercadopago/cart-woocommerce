const commonGlobalSetup = require('../global-setup');
const { wpOption } = require('../helpers/wp-env');

const LOCAL_HTTPS_URL = /^https:\/\/localhost:\d{2,5}$/;

module.exports = async function iosGlobalSetup() {
  await commonGlobalSetup();

  const storeUrl = process.env.IOS_STORE_URL || 'https://localhost:8443';
  if (!LOCAL_HTTPS_URL.test(storeUrl)) {
    throw new Error('[iOS E2E] IOS_STORE_URL deve usar https://localhost:<porta>.');
  }

  if (wpOption('siteurl', storeUrl) === null || wpOption('home', storeUrl) === null) {
    throw new Error('[iOS E2E] Não foi possível configurar siteurl/home para o HTTPS local.');
  }
};
