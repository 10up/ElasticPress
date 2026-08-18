import { test, expect } from '../fixtures.js';
import { goToAdminPage, isEpIo, wpCli } from '../utils.js';

/**
 * Tests for the Subscription Token field on the ElasticPress Settings page.
 */
test.describe('Settings page Subscription Token field', { tag: '@group1' }, () => {
	let epHost = '';
	let epCredentials = '';
	let username = '';
	let token = '';

	test.beforeAll(async () => {
		if (!isEpIo()) {
			return;
		}

		epHost = (await wpCli('config get EP_HOST', true)).toString().trim();
		epCredentials = (await wpCli('config get EP_CREDENTIALS', true)).toString().trim();

		const separator = epCredentials.indexOf(':');
		username = epCredentials.slice(0, separator);
		token = epCredentials.slice(separator + 1);

		// Delete the constants from wp-config.php
		await wpCli('config delete EP_HOST');
		await wpCli('config delete EP_CREDENTIALS');
	});

	test.afterAll(async () => {
		if (!isEpIo()) {
			return;
		}

		// Add the constants back to wp-config.php
		await wpCli(`config set EP_HOST '${epHost}'`);
		await wpCli(`config set EP_CREDENTIALS '${epCredentials}'`);
	});

	test('Can hide the token after saving the credentials', async ({ loggedInPage }) => {
		test.skip(!isEpIo(), 'Requires ElasticPress.io credentials in wp-config.php.');

		expect(epHost).toBeTruthy();
		expect(username).toBeTruthy();
		expect(token).toBeTruthy();

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		await loggedInPage.fill('#ep_host', epHost);
		await loggedInPage.fill('#ep_username', username);
		await loggedInPage.fill('#ep_token', token);
		await loggedInPage.click('#submit');
		await loggedInPage.waitForLoadState('networkidle');

		await expect(loggedInPage.locator('#ep_username')).toHaveValue(username);

		const tokenInput = loggedInPage.locator('#ep_token');
		await expect(tokenInput).toBeVisible();
		await expect(tokenInput).toHaveAttribute('type', 'password');
		await expect(tokenInput).toHaveAttribute('autocomplete', 'off');
		await expect(tokenInput).toHaveAttribute('placeholder', '••••••••');
		await expect(tokenInput).toHaveValue('');

		// The token must not appear anywhere in the rendered HTML.
		const pageHtml = await loggedInPage.content();
		expect(pageHtml.includes(token)).toBe(false);
	});

	test('Can show a connection error notice for an invalid token', async ({ loggedInPage }) => {
		test.skip(!isEpIo(), 'Requires ElasticPress.io credentials in wp-config.php.');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		await loggedInPage.fill('#ep_token', 'an-invalid-token');
		await loggedInPage.click('#submit');
		await loggedInPage.waitForLoadState('networkidle');

		await expect(
			loggedInPage.getByText(
				'It was not possible to connect to your Elasticsearch server. Please check your settings and try again.',
			),
		).toBeVisible();
	});

	test('Can empty the saved settings after clearing the host and token', async ({
		loggedInPage,
	}) => {
		test.skip(!isEpIo(), 'Requires ElasticPress.io credentials in wp-config.php.');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-settings');

		await loggedInPage.fill('#ep_host', '');
		await loggedInPage.fill('#ep_username', '');
		await loggedInPage.fill('#ep_token', '');
		await loggedInPage.check('#ep_remove_token');
		await loggedInPage.click('#submit');
		await loggedInPage.waitForLoadState('networkidle');

		await expect(loggedInPage.locator('#ep_host')).toHaveValue('');
		await expect(loggedInPage.locator('#ep_username')).toHaveValue('');
		await expect(loggedInPage.locator('#ep_token')).toHaveValue('');
		await expect(loggedInPage.locator('#ep_token')).toHaveAttribute('placeholder', '');
	});
});
