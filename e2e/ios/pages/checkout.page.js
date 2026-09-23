import { BasePage } from './base.page';
import { By, Select, until } from 'selenium-webdriver';

const CUSTOM_CLASSIC = '#payment_method_woo-mercado-pago-custom';
const CUSTOM_BLOCKS = '#radio-control-wc-payment-method-options-woo-mercado-pago-custom';

export class CheckoutPage extends BasePage {
  async fillBilling(user, checkoutMode) {
    if (checkoutMode === 'classic') {
      await this.fillClassicBilling(user);
      return;
    }
    await this.fillBlocksBilling(user);
  }

  async fillClassicBilling(user) {
    await this.replaceValue('#billing_first_name', user.firstName);
    await this.replaceValue('#billing_last_name', user.lastName);
    await new Select(await this.displayed('#billing_country')).selectByValue(user.address.countryId);
    await this.driver.sleep(1500);

    await this.replaceValue('#billing_address_1', user.address.street);
    await this.replaceValue('#billing_city', user.address.city);

    if (await this.isDisplayed('#billing_state')) {
      await new Select(await this.element('#billing_state')).selectByValue(user.address.state);
    }
    await this.setValueIfDisplayed('#billing_postcode', user.address.zip);
    await this.replaceValue('#billing_phone', user.phone || '11999999999');
    await this.replaceValue('#billing_email', user.email);

    if (await this.isDisplayed('#billing_persontype')) {
      await new Select(await this.element('#billing_persontype')).selectByValue('1');
      await this.driver.sleep(500);
      await this.setValueIfDisplayed('#billing_cpf', user.document);
    }
    await this.setValueIfDisplayed('#billing_number', user.address.number || '122');

    await this.waitForCheckoutIdle();
    const postcodeElements = await this.driver.findElements(By.css('#billing_postcode'));
    if (postcodeElements.length && await postcodeElements[0].isDisplayed().catch(() => false)) {
      if (!await postcodeElements[0].getAttribute('value')) {
        await postcodeElements[0].sendKeys(user.address.zip);
      }
    }
  }

  async fillBlocksBilling(user) {
    const shippingFields = await this.driver.findElements(By.css('#shipping-first_name'));
    if (shippingFields.length && !await shippingFields[0].isDisplayed().catch(() => false)) {
      const editButtons = await this.driver.findElements(By.css('.wc-block-components-address-card__edit'));
      for (const button of editButtons) {
        if (await button.isDisplayed().catch(() => false)) {
          await button.click();
          break;
        }
      }
    }

    let prefix;
    try {
      prefix = await this.driver.wait(
        async () => {
          if (await this.isDisplayed('#shipping-first_name', 250)) return 'shipping';
          if (await this.isDisplayed('#billing-first_name', 250)) return 'billing';
          return false;
        },
        30000,
        'WooCommerce Blocks did not render a shipping or billing address form',
        250,
      );
    } catch (error) {
      const state = await this.driver.executeScript(() => ({
        path: window.location.pathname,
        title: document.title,
        bodyClasses: document.body.className,
        checkoutClasses: document.querySelector('.wp-block-woocommerce-checkout')?.className || '',
        inputIds: Array.from(document.querySelectorAll('input[id]'), (element) => element.id).slice(0, 30),
        addressCardCount: document.querySelectorAll('.wc-block-components-address-card').length,
      }));
      throw new Error(
        `WooCommerce Blocks address form is unavailable. Safe page state: ${JSON.stringify(state)}.`,
        { cause: error },
      );
    }

    await this.replaceValue('#email', user.email);
    await this.replaceValue(`#${prefix}-first_name`, user.firstName);
    await this.replaceValue(`#${prefix}-last_name`, user.lastName);
    await new Select(await this.displayed(`#${prefix}-country`)).selectByValue(user.address.countryId);
    await this.driver.sleep(1500);

    await this.replaceValue(`#${prefix}-address_1`, user.address.street);
    await this.replaceValue(`#${prefix}-city`, user.address.city);
    if (await this.isDisplayed(`#${prefix}-state`)) {
      await new Select(await this.element(`#${prefix}-state`)).selectByValue(user.address.state);
    }
    await this.setValueIfDisplayed(`#${prefix}-postcode`, user.address.zip);
    await this.setValueIfDisplayed(`#${prefix}-phone`, user.phone || '11999999999');
    await this.waitForCheckoutIdle();
  }

  async selectCustomPayment(checkoutMode) {
    if (checkoutMode === 'classic') {
      const radio = await this.displayed(CUSTOM_CLASSIC);
      if (!await radio.isSelected()) {
        const label = await this.displayed(`label[for="${CUSTOM_CLASSIC.slice(1)}"]`);
        await label.click();
      }
    } else {
      const radio = await this.displayed(CUSTOM_BLOCKS);
      if (!await radio.isSelected()) await radio.click();
    }
    await this.waitForCheckoutIdle();
    // Blocks updates its Store API state after the radio becomes selected. The
    // spinner may disappear before onPaymentSetup has settled in mobile Safari.
    await this.driver.sleep(3000);
    await this.waitForCheckoutIdle();
  }

  async placeOrder(checkoutMode) {
    if (checkoutMode === 'blocks') {
      await this.waitForCheckoutIdle();
    }
    const selector = checkoutMode === 'classic'
      ? '#place_order'
      : '.wc-block-components-checkout-place-order-button';
    const button = await this.displayed(selector);
    await this.driver.wait(until.elementIsEnabled(button), 15000);
    await button.click();
  }

  async waitForOrderReceived(timeout = Number(process.env.IOS_ORDER_TIMEOUT || 90000)) {
    try {
      await this.driver.wait(
        async () => /\/order-received\//.test(await this.driver.getCurrentUrl()),
        timeout,
        'Approved payment did not reach order-received',
        500,
      );
    } catch (error) {
      const noticeCount = await this.driver.executeScript(() => document.querySelectorAll(
        '.woocommerce-error, .woocommerce-notices-wrapper, .wc-block-components-notice-banner__content',
      ).length);
      const detail = noticeCount ? ` Visible checkout notices: ${noticeCount}.` : '';
      const cardState = await this.driver.executeScript(() => ({
        tokenLength: document.querySelector('#cardTokenId')?.value?.length || 0,
        paymentMethodId: document.querySelector('#paymentMethodId')?.value || '',
        installments: document.querySelector('#form-checkout__installments')?.value || '',
        hiddenInstallments: document.querySelector('#cardInstallments')?.value || '',
        cardNumberError: document.querySelector('#form-checkout__cardNumber-container')?.classList.contains('mp-error') || false,
        expirationError: document.querySelector('#form-checkout__expirationDate-container')?.classList.contains('mp-error') || false,
        securityCodeError: document.querySelector('#form-checkout__securityCode-container')?.classList.contains('mp-error') || false,
      }));
      throw new Error(
        `Approved payment did not reach order-received.${detail} ` +
        `Safe card state: ${JSON.stringify(cardState)}.`,
        { cause: error },
      );
    }
    await this.displayed('.woocommerce-thankyou-order-received', 30000);
  }

  async visibleCheckoutErrors() {
    const selector = [
      '.woocommerce-error',
      '.wc-block-store-notice.wc-block-components-notice-banner.is-error',
      '.wc-block-components-notice-banner.is-error',
    ].join(', ');
    return this.driver.executeScript((noticeSelector) => Array.from(document.querySelectorAll(noticeSelector))
      .filter((notice) => {
        const style = window.getComputedStyle(notice);
        return style.display !== 'none' && style.visibility !== 'hidden';
      })
      .map((notice) => (notice.textContent || '').replace(/\s+/g, ' ').trim())
      .filter(Boolean), selector);
  }

  async waitForPaymentRejected(previousNotices = [], timeout = Number(process.env.IOS_ORDER_TIMEOUT || 90000)) {
    const previous = new Set(previousNotices);
    await this.driver.wait(
      async () => {
        if (/\/order-received\//.test(await this.driver.getCurrentUrl())) {
          throw new Error('Rejected payment unexpectedly reached order-received');
        }
        const notices = await this.visibleCheckoutErrors();
        return notices.some((notice) => !previous.has(notice));
      },
      timeout,
      'Rejected payment did not show a new checkout error',
      500,
    );
  }

}
