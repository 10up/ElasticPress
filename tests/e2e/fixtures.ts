import { test as base, Page, Locator } from '@playwright/test';
import { login } from './utils';

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

export { expect, Page, Locator } from '@playwright/test';
