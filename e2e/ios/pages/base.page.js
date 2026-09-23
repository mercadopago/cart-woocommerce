import { By, until } from 'selenium-webdriver';

export class BasePage {
  constructor(driver) {
    this.driver = driver;
  }

  async element(selector, timeout = 30000) {
    return this.driver.wait(until.elementLocated(By.css(selector)), timeout);
  }

  async displayed(selector, timeout = 30000) {
    const element = await this.element(selector, timeout);
    await this.driver.wait(until.elementIsVisible(element), timeout);
    return element;
  }

  async isDisplayed(selector, timeout = 1000) {
    return this.driver.wait(
      async () => {
        const elements = await this.driver.findElements(By.css(selector));
        for (const element of elements) {
          if (await element.isDisplayed().catch(() => false)) return true;
        }
        return false;
      },
      timeout,
      `Element did not become visible: ${selector}`,
      100,
    ).then(() => true).catch(() => false);
  }

  async setValueIfDisplayed(selector, value, timeout = 1000) {
    if (!await this.isDisplayed(selector, timeout)) return false;
    await this.replaceValue(selector, value);
    return true;
  }

  async replaceValue(selector, value) {
    await this.displayed(selector);
    const updated = await this.driver.executeScript((targetSelector, nextValue) => {
      const target = document.querySelector(targetSelector);
      const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
      if (!target || !setter) return false;
      target.focus();
      setter.call(target, String(nextValue ?? ''));
      const applied = target.value === String(nextValue ?? '');
      target.dispatchEvent(new InputEvent('input', { bubbles: true, composed: true }));
      target.dispatchEvent(new Event('change', { bubbles: true }));
      target.blur();
      return applied;
    }, selector, value);
    if (!updated) throw new Error(`[iOS E2E] Safari could not update field: ${selector}`);
  }

  async waitForCheckoutIdle(timeout = 15000) {
    await this.driver.wait(
      async () => this.driver.executeScript(
        'return !document.querySelector(".blockUI.blockOverlay, .wc-block-components-spinner")',
      ),
      timeout,
      'WooCommerce checkout did not become idle',
      250,
    ).catch(() => {
      console.warn('[iOS E2E] Checkout did not become idle before the timeout; continuing with downstream checks.');
    });
  }

  async pagePath() {
    const current = await this.driver.getCurrentUrl();
    try {
      return new URL(current).pathname;
    } catch {
      return current.split(/[?#]/)[0];
    }
  }
}
