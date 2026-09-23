import { test as base, expect } from '@playwright/test';
import { Builder } from 'selenium-webdriver';
import { Command } from 'selenium-webdriver/lib/command';
import { createEvidenceRecorder } from './helpers/evidence';

function required(name, value) {
  if (!value) {
    throw new Error(`[iOS E2E] ${name} is required.`);
  }
  return value;
}

function appiumConnection() {
  const platformVersion = process.env.IOS_PLATFORM_VERSION;
  const udid = process.env.IOS_UDID;
  const fallback = `http://${process.env.APPIUM_HOST || '127.0.0.1'}:${Number(process.env.APPIUM_PORT || 4723)}${process.env.APPIUM_PATH || '/'}`;
  const serverUrl = new URL(process.env.APPIUM_URL || fallback);
  if (
    !['http:', 'https:'].includes(serverUrl.protocol) ||
    !['127.0.0.1', 'localhost'].includes(serverUrl.hostname)
  ) {
    throw new Error('[iOS E2E] APPIUM_URL must use a loopback HTTP(S) endpoint.');
  }
  return {
    serverUrl: serverUrl.toString(),
    capabilities: {
      platformName: 'iOS',
      browserName: 'Safari',
      'appium:automationName': 'XCUITest',
      'appium:deviceName': required('IOS_DEVICE_NAME', process.env.IOS_DEVICE_NAME),
      ...(platformVersion ? { 'appium:platformVersion': platformVersion } : {}),
      ...(udid ? { 'appium:udid': udid } : {}),
      'appium:newCommandTimeout': 240,
      // Fresh Safari/WebKit instances can expose their first debuggable context
      // slowly after an erased-simulator boot.
      'appium:webviewConnectTimeout': 90000,
    },
  };
}

export const test = base.extend({
  mobile: async ({}, use) => {
    const { serverUrl, capabilities } = appiumConnection();
    const driver = await new Builder()
      .disableEnvironmentOverrides()
      .usingServer(serverUrl)
      .withCapabilities(capabilities)
      .build();
    try {
      const executor = driver.getExecutor();
      executor.defineCommand('getAppiumContexts', 'GET', '/session/:sessionId/contexts');
      executor.defineCommand('setAppiumContext', 'POST', '/session/:sessionId/context');
      executor.defineCommand('hideAppiumKeyboard', 'POST', '/session/:sessionId/appium/device/hide_keyboard');
      driver.getAppiumContexts = () => driver.execute(new Command('getAppiumContexts'));
      driver.setAppiumContext = (name) => driver.execute(
        new Command('setAppiumContext').setParameter('name', name),
      );
      driver.hideAppiumKeyboard = () => driver.execute(new Command('hideAppiumKeyboard'));
      const contexts = await driver.getAppiumContexts();
      driver.webContext = contexts.find((name) => name !== 'NATIVE_APP');
      if (!driver.webContext) throw new Error('[iOS E2E] XCUITest did not expose a Safari web context.');
      await driver.setAppiumContext(driver.webContext);
      await driver.manage().setTimeouts({
        implicit: 0,
        pageLoad: 60000,
        script: 30000,
      });
      await use(driver);
    } finally {
      await driver.quit().catch(() => {});
    }
  },

  evidence: async ({ mobile }, use, testInfo) => {
    const recorder = await createEvidenceRecorder(mobile, testInfo);
    await use(recorder);
  },
});

export { expect };
