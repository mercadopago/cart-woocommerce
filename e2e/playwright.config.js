// @ts-check
const { defineConfig, devices } = require("@playwright/test");

require("dotenv").config();

// Specs that mutate a store-wide option (e.g. the access token in the refund auth-error
// scenario) are tagged @serial-store. Isolation is enforced by run-all-report.sh running
// MLB in two sequential invocations: all non-@serial-store tests first (workers=2), then
// @serial-store tests alone (workers=1). Direct `npx playwright test tests/mlb/` invocations
// outside the runner get workers=2 — use --grep-invert /@serial-store/ to avoid the race,
// or accept that CI (workers=1) enforces it regardless.

const chromiumUse = {
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
};

const projects = [{ name: "chromium", use: chromiumUse }];

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
    /* CI retries for infra noise; local retries absorb MP sandbox latency variance
       on "successful payment" flows (payment approval/settlement can intermittently
       exceed the per-test timeout — the same test passes on a re-attempt). */
    retries: process.env.CI ? 3 : 2,
    /* Each test gets an isolated browser context (separate cookies/session).
       Full-checkout flows hit a single shared store + the real MP sandbox API, so
       too many parallel workers cause contention (orders time out intermittently).
       run-all-report.sh isolates @serial-store (MLB) via two sequential invocations.
       CI is always 1 (fully serial, max determinism). */
    workers: process.env.CI ? 1 : 2,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: "html",
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        // baseURL: 'http://127.0.0.1:3000',

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: "on-first-retry",
        /* Save a screenshot whenever a test fails — stored in test-results/. */
        screenshot: "only-on-failure"
    },

    /* Configure projects for major browsers (built above, conditional on CI). */
    projects,

    /* Run your local dev server before starting the tests */
    // webServer: {
    //   command: 'npm run start',
    //   url: 'http://127.0.0.1:3000',
    //   reuseExistingServer: !process.env.CI,
    // },
});
