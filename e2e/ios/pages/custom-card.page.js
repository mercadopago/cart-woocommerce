import { BasePage } from './base.page';
import { By, Select } from 'selenium-webdriver';

const INSTALLMENTS_SELECT = '#form-checkout__installments';
const INSTALLMENTS_HIDDEN = '#cardInstallments';

export class CustomCardPage extends BasePage {
  async typeInSecureFrame(frameName, value, delay = 40) {
    const frame = await this.displayed(`iframe[name="${frameName}"]`, 30000);
    await this.driver.executeScript(
      'arguments[0].scrollIntoView({ block: "center", inline: "nearest" });',
      frame,
    );
    await this.driver.sleep(500);
    const frameRect = await this.driver.executeScript((element) => {
      const rect = element.getBoundingClientRect();
      return {
        x: rect.x,
        y: rect.y,
        width: rect.width,
        height: rect.height,
      };
    }, frame);

    const webContext = this.driver.webContext;
    if (!webContext || !this.driver.setAppiumContext) {
      throw new Error('[iOS E2E] Appium context switching is unavailable.');
    }
    try {
      await this.driver.setAppiumContext('NATIVE_APP');
      const textFields = await this.driver.findElements(By.xpath('//XCUIElementTypeTextField'));
      let nativeInput;
      let nativeRect;
      let bestScore = Number.POSITIVE_INFINITY;
      const expectedNativeY = frameRect.y + (this.nativeWebOffsetY ?? 0);
      for (const element of textFields) {
        const rect = await element.getRect().catch(() => null);
        if (!rect) continue;
        const score = Math.abs(rect.x - frameRect.x) * 3
          + Math.abs(rect.width - frameRect.width) * 3
          + Math.abs(rect.y - expectedNativeY);
        if (score < bestScore) {
          nativeInput = element;
          nativeRect = rect;
          bestScore = score;
        }
      }
      if (!nativeInput) {
        throw new Error(
          `[iOS E2E] XCUITest could not map a native field to ${frameName} ` +
          `(frameRect=${JSON.stringify(frameRect)}, textFields=${textFields.length}).`,
        );
      }
      if (this.nativeWebOffsetY == null) {
        this.nativeWebOffsetY = nativeRect.y - frameRect.y;
      }
      await nativeInput.clear().catch(() => {});
      await nativeInput.click();
      for (const character of String(value)) {
        await this.driver.actions({ async: true }).sendKeys(character).perform();
        if (delay) await this.driver.sleep(delay);
      }
      await this.driver.hideAppiumKeyboard?.().catch(() => {});
      await this.driver.sleep(300);
    } finally {
      await this.driver.setAppiumContext(webContext).catch(() => {
        console.warn('[iOS E2E] Failed to restore the Safari web context after native input.');
      });
    }
  }

  async fill(card, form) {
    const digits = String(card.number).replace(/\D/g, '');
    // Mercado Pago's PCI secure field intentionally does not expose its value
    // to the web context. The public SDK state (installments becoming available)
    // and the approved order are the observable proof that all digits arrived.
    await this.typeInSecureFrame(
      'cardNumber',
      digits,
      50,
    );
    if (!await this.waitForCardRecognition(8000)) {
      await this.typeInSecureFrame('cardNumber', digits, 50);
      if (!await this.waitForCardRecognition(15000)) {
        const state = await this.driver.executeScript(() => ({
          paymentMethodId: document.querySelector('#paymentMethodId')?.value || '',
          cardNumberError: document.querySelector('#form-checkout__cardNumber-container')
            ?.classList.contains('mp-error') || false,
        }));
        throw new Error(`[iOS E2E] Card BIN was not recognized. Safe state: ${JSON.stringify(state)}.`);
      }
    }

    await this.typeInSecureFrame(
      'expirationDate',
      String(card.date).replace('/', ''),
      30,
    );
    await this.typeInSecureFrame(
      'securityCode',
      card.code,
      30,
    );

    await this.replaceValue('#form-checkout__cardholderName', form.name);

    if (await this.isDisplayed('#form-checkout__identificationType')) {
      const type = await this.element('#form-checkout__identificationType');
      await new Select(type).selectByValue(form.docType);
      await this.setValueIfDisplayed('[name="identificationNumber"]', form.docNumber);
    }

    await this.driver.sleep(1000);
  }

  async waitForCardRecognition(timeout) {
    return this.driver.wait(
      async () => this.driver.executeScript((selector) => {
        const paymentMethodId = document.querySelector('#paymentMethodId')?.value;
        const installment = document.querySelector(`${selector} option[value="1"]`);
        return Boolean(paymentMethodId && installment && !installment.disabled);
      }, INSTALLMENTS_SELECT),
      timeout,
      'Card BIN was not recognized',
      250,
    ).then(() => true).catch(() => false);
  }

  async waitForIosAutoSelection(timeout = 15000) {
    await this.driver.wait(
      async () => {
        const state = await this.installmentsState(false);
        return state.selectValue === '1';
      },
      timeout,
      'iOS Safari did not auto-select an enabled installment option',
      250,
    );
  }

  async selectApprovedInstallment(timeout = 15000) {
    await this.driver.wait(
      async () => this.driver.executeScript((selector) => {
        const option = document.querySelector(`${selector} option[value="1"]`);
        return Boolean(option && !option.disabled);
      }, INSTALLMENTS_SELECT),
      timeout,
      'Approved installment option did not become available',
      250,
    );
    const select = new Select(await this.displayed(INSTALLMENTS_SELECT));
    await select.selectByValue('1');
    await this.driver.sleep(500);
  }

  async installmentsState(selectTouched) {
    const state = await this.driver.executeScript((selectSelector, hiddenSelector) => {
      const select = document.querySelector(selectSelector);
      const hidden = document.querySelector(hiddenSelector);
      const placeholder = select?.querySelector('option[disabled]');
      return {
        selectValue: select?.value || '',
        hiddenValue: hidden?.value || '',
        placeholderDisabled: Boolean(placeholder?.disabled),
      };
    }, INSTALLMENTS_SELECT, INSTALLMENTS_HIDDEN);

    return { ...state, selectTouched };
  }

}
