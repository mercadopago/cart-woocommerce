const path = require('path');
const { defineConfig } = require('@playwright/test');

// The shared checkout data reads its values from e2e/.env at import time. Load it
// before Playwright discovers the iOS specs so no credential or card is duplicated
// in this suite.
require('dotenv').config({ path: path.resolve(__dirname, '..', '.env') });

module.exports = defineConfig({
  testDir: './tests',
  outputDir: './test-results',
  globalSetup: './global-setup.js',
  // A clean simulator may spend several minutes compiling/starting WebDriverAgent
  // before the first test body runs.
  timeout: 300000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: !!process.env.CI,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  projects: [
    {
      name: 'ios-simulator-safari',
      testMatch: '**/*.spec.js',
    },
  ],
});
