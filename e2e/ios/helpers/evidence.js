import fs from 'fs/promises';
import path from 'path';
import { randomUUID } from 'node:crypto';
import { By } from 'selenium-webdriver';

const EVIDENCE_ROOT = path.resolve(__dirname, '..', 'evidence');
const DEFAULT_EXECUTION_ID = randomUUID();

function slug(value) {
  return String(value)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 100) || 'scenario';
}

function safeExecutionId() {
  const value = process.env.EXECUTION_ID || process.env.IOS_EXECUTION_ID || DEFAULT_EXECUTION_ID;
  if (value === '.' || value === '..') {
    throw new Error('[iOS E2E] Invalid evidence execution identifier.');
  }
  return /^[A-Za-z0-9._-]{1,120}$/.test(value) ? value : slug(value);
}

function isContained(baseDirectory, candidate) {
  const relative = path.relative(baseDirectory, candidate);
  return relative !== '' && !relative.startsWith(`..${path.sep}`) && !path.isAbsolute(relative);
}

function safeBeforeSubmitPayload(state) {
  return {
    schemaVersion: 1,
    site: 'MLB',
    checkoutMode: state.checkoutMode,
    pagePath: state.pagePath,
    paymentMethod: 'woo-mercado-pago-custom',
    installments: {
      selectValue: state.installments.selectValue,
      hiddenValue: state.installments.hiddenValue,
      placeholderDisabled: Boolean(state.installments.placeholderDisabled),
      selectTouched: Boolean(state.installments.selectTouched),
    },
  };
}

async function firstDisplayed(driver, selectors) {
  for (const selector of selectors) {
    const elements = await driver.findElements(By.css(selector));
    for (const element of elements) {
      if (await element.isDisplayed().catch(() => false)) return element;
    }
  }
  throw new Error(`[iOS E2E] No safe evidence element is visible: ${selectors.join(', ')}`);
}

async function writeElementScreenshot(element, filePath) {
  const screenshot = await element.takeScreenshot();
  await fs.writeFile(filePath, Buffer.from(screenshot, 'base64'), { mode: 0o600 });
}

function checkoutEvidenceSections(checkoutMode) {
  const common = [
    {
      fileName: 'card-number-filled.png',
      selectors: ['.mp-checkout-custom-card-form > .mp-checkout-custom-card-row:first-child'],
    },
    {
      fileName: 'cardholder-filled.png',
      selectors: ['#mp-card-holder-div'],
    },
    {
      fileName: 'card-security-filled.png',
      selectors: ['.mp-checkout-custom-card-row.mp-checkout-custom-dual-column-row'],
    },
    {
      fileName: 'document-filled.png',
      optional: true,
      selectors: ['#mp-doc-div'],
    },
    {
      fileName: 'installments-filled.png',
      selectors: ['#mp-checkout-custom-installments-card', '#form-checkout__installments'],
    },
  ];

  if (checkoutMode === 'blocks') {
    return [
      {
        fileName: 'contact-filled.png',
        selectors: ['.wc-block-checkout__contact-fields', '.wc-block-checkout__contact-fields-block'],
      },
      {
        fileName: 'customer-filled.png',
        selectors: [
          '.wc-block-checkout__shipping-fields',
          '.wc-block-checkout__billing-fields',
          '.wc-block-components-address-card',
        ],
      },
      ...common,
      {
        fileName: 'order-summary.png',
        selectors: ['.wp-block-woocommerce-checkout-order-summary-block'],
      },
    ];
  }

  return [
    {
      fileName: 'customer-filled.png',
      selectors: ['.woocommerce-billing-fields', '.woocommerce-billing-fields__field-wrapper'],
    },
    ...common,
    {
      fileName: 'order-summary.png',
      selectors: ['.woocommerce-checkout-review-order-table', '#order_review'],
    },
  ];
}

export async function createEvidenceRecorder(driver, testInfo) {
  const scenario = slug(testInfo.titlePath.join('-'));
  const executionDirectory = path.resolve(EVIDENCE_ROOT, safeExecutionId());
  if (!isContained(EVIDENCE_ROOT, executionDirectory)) {
    throw new Error('[iOS E2E] Evidence destination is outside the allowed directory.');
  }

  await fs.mkdir(EVIDENCE_ROOT, { recursive: true, mode: 0o700 });
  await fs.mkdir(executionDirectory, { recursive: true, mode: 0o700 });
  const executionStats = await fs.lstat(executionDirectory);
  if (executionStats.isSymbolicLink()) {
    throw new Error('[iOS E2E] Evidence destination cannot be a symbolic link.');
  }

  const realRoot = await fs.realpath(EVIDENCE_ROOT);
  const realExecutionDirectory = await fs.realpath(executionDirectory);
  if (!isContained(realRoot, realExecutionDirectory)) {
    throw new Error('[iOS E2E] Evidence destination is outside the allowed directory.');
  }

  const directory = path.resolve(realExecutionDirectory, scenario);
  await fs.mkdir(directory, { recursive: true, mode: 0o700 });
  const realDirectory = await fs.realpath(directory);
  if (!isContained(realRoot, realDirectory)) {
    throw new Error('[iOS E2E] Evidence destination is outside the allowed directory.');
  }

  return {
    directory: realDirectory,

    async captureFormFilled(checkoutMode) {
      const sections = checkoutEvidenceSections(checkoutMode);
      for (const section of sections) {
        let element;
        try {
          element = await firstDisplayed(driver, section.selectors);
        } catch (error) {
          if (section.optional) continue;
          throw error;
        }
        await writeElementScreenshot(element, path.join(realDirectory, section.fileName));
      }
    },

    async writeBeforeSubmit(state) {
      const payload = safeBeforeSubmitPayload(state);
      await fs.writeFile(
        path.join(realDirectory, 'before-submit.json'),
        `${JSON.stringify(payload, null, 2)}\n`,
        { encoding: 'utf8', mode: 0o600 },
      );
    },

    async captureOrderReceived() {
      // Crop to the confirmation message; order details below it may contain PII.
      const element = await firstDisplayed(driver, [
        '.woocommerce-thankyou-order-received',
        '.woocommerce-order-received .entry-title',
      ]);
      await writeElementScreenshot(element, path.join(realDirectory, 'order-received.png'));
    },

    async capturePaymentRejected() {
      const element = await firstDisplayed(driver, [
        '.woocommerce-error',
        '.wc-block-store-notice.wc-block-components-notice-banner.is-error',
        '.wc-block-components-notice-banner.is-error',
      ]);
      await writeElementScreenshot(element, path.join(realDirectory, 'payment-rejected.png'));
    },
  };
}
