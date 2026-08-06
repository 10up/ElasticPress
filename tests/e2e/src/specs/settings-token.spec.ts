import { test, expect } from '../fixtures.js';
import { goToAdminPage, isEpIo, wpCli } from '../utils.js';

const FAKE_TOKEN = 'super-secret-token';
const FAKE_USERNAME = 'ep-test-user';
const EPIO_HOST = 'https://elasticpress.io';

/**
 * Tests for the Subscription Token field on the ElasticPress Settings page.
 *
 * The token value is never rendered in the DOM and an empty POST value
 * preserves the previously stored token.
 */
test.describe('Settings page Subscription Token field', { tag: '@group1' }, () => {
	test.beforeAll(async () => {
		if (isEpIo()) {
			return;
		}

		// Seed a known token and force is_epio() to true via the host pattern
		// so the credentials row renders on the ElasticPress.io tab.
		await wpCli(`option update ep_host '${EPIO_HOST}' --format=json`);
		await wpCli(
			`option update ep_credentials '{"username":"${FAKE_USERNAME}","token":"${FAKE_TOKEN}"}' --format=json`,
		);
	});

	test.afterAll(async () => {
		if (isEpIo()) {
			return;
		}

		// Clean up the seeded options so other specs are not affected.
		await wpCli('option delete ep_host');
		await wpCli('option delete ep_credentials');
	});

	test('Token value is not exposed in the page', async ({ loggedInPage }) => {
		test.skip(isEpIo(), 'Uses locally seeded credentials.');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		const tokenInput = loggedInPage.locator('#ep_token');
		await expect(tokenInput).toBeVisible();
		await expect(tokenInput).toHaveAttribute('type', 'password');
		await expect(tokenInput).toHaveAttribute('autocomplete', 'off');
		await expect(tokenInput).toHaveValue('');

		// The actual token must not appear anywhere in the rendered HTML.
		const pageHtml = await loggedInPage.content();
		expect(pageHtml).not.toContain(FAKE_TOKEN);
	});

	test('Saving without changing the token preserves the stored value', async ({
		loggedInPage,
	}) => {
		test.skip(isEpIo(), 'Uses locally seeded credentials.');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		// Submit the form with the token field left untouched.
		await loggedInPage.click('#submit');
		await loggedInPage.waitForLoadState('networkidle');

		const stored = await wpCli('option get ep_credentials --format=json');
		expect(stored?.toString()).toContain(FAKE_TOKEN);
	});

	test('Saving a new token value updates the stored value', async ({ loggedInPage }) => {
		test.skip(isEpIo(), 'Uses locally seeded credentials.');

		const newToken = 'rotated-token';

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		await loggedInPage.fill('#ep_token', newToken);
		await loggedInPage.click('#submit');
		await loggedInPage.waitForLoadState('networkidle');

		const stored = await wpCli('option get ep_credentials --format=json');
		expect(stored?.toString()).toContain(newToken);
		expect(stored?.toString()).not.toContain(FAKE_TOKEN);
	});
});
