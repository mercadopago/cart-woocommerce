// @ts-check
const { defineConfig, devices } = require("@playwright/test");

require("dotenv").config();

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
    timeout: 60000,
    testDir: "./tests",
    globalSetup: './global-setup.js',
    /* Run tests in files in parallel */
    fullyParallel: false,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only — local runs fail fast */
    retries: process.env.CI ? 3 : 0,
    /* Each test gets an isolated browser context (separate cookies/session),
       so parallel workers don't conflict. 4 workers for fast local execution. */
    workers: process.env.CI ? 1 : 4,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: "html",
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        // baseURL: 'http://127.0.0.1:3000',

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: "on-first-retry"
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: "chromium",
            use: {
                ...devices["Desktop Chrome"],
                launchOptions: {
                    // Use full Chromium (not headless_shell) — headless_shell ignores
                    // --unsafely-treat-insecure-origin-as-secure which is required for
                    // WC Blocks checkout (crypto.randomUUID needs Secure Context).
                    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        // WC Blocks uses crypto.randomUUID() which requires Secure Context.
                        // Inside Docker, Chromium accesses via host.docker.internal (not localhost),
                        // which is not a Secure Context by default. This flag fixes it.
                        '--unsafely-treat-insecure-origin-as-secure=http://host.docker.internal:' + (process.env.PORT || '8080'),
                    ],
                },
            },
         }//,

        // {
        //     name: "firefox",
        //     use: { ...devices["Desktop Firefox"] }
        // },

        // {
        //     name: "webkit",
        //     use: { ...devices["Desktop Safari"] }
        // }

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        // },
    ]

    /* Run your local dev server before starting the tests */
    // webServer: {
    //   command: 'npm run start',
    //   url: 'http://127.0.0.1:3000',
    //   reuseExistingServer: !process.env.CI,
    // },
});
