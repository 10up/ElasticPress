import { test as base } from '@playwright/test';
import type { Page } from 'playwright';
import { login } from './utils.js';

// Declare the types of your fixtures
type LoggedInFixtures = {
	loggedInPage: Page;
};

// Extend the base test with your custom fixtures
export const test = base.extend<LoggedInFixtures>({
	loggedInPage: async ({ page }, use) => {
		await login(page);
		await use(page);
	},
});

export { expect } from '@playwright/test';
export type { Locator, Page } from 'playwright';
