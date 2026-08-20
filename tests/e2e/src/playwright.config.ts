import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
	testDir: './specs',
	/* Run tests in files in parallel */
	fullyParallel: false,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !!process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* The suite runs against a single WordPress install and the tests toggle
	 * features and weighting globally, so parallel workers interfere with each
	 * other. */
	workers: 1,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
				['list'],
				['github'],
				[
					'html',
					{
						open: 'never',
						outputFolder: process.env.PLAYWRIGHT_HTML_OUTPUT_DIR || 'playwright-report',
					},
				],
			]
		: 'html',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* Base URL to use in actions like `await page.goto('/')`. */
		baseURL: 'http://localhost:8889',

		/* Collect a trace on failure, including the first attempt. */
		trace: 'retain-on-failure',

		// Capture screenshot after each test failure.
		screenshot: 'only-on-failure',

		// Record video on failure.
		video: 'retain-on-failure',

		// GitHub-hosted Ubuntu already has Google Chrome. Using it in CI
		// skips Playwright's Chromium download and apt install-deps.
		...(process.env.CI ? { channel: 'chrome' as const } : {}),
	},

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'setup',
			testMatch: /global\.setup\.ts/,
		},
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
			dependencies: ['setup'],
		},
	],
});
