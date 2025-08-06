import { test, expect } from '../fixtures';
import { goToAdminPage, wpCli } from '../utils';

test.describe('WordPress basic actions', { tag: '@group2' }, () => {
	test.beforeAll('EP Sync', async () => {
		wpCli('elasticpress sync --setup --yes');
	});

	test('has <title> tag', async ({ page }) => {
		await page.goto('/');
		await expect(page.locator('title')).toBeAttached();
	});

	test('can login', async ({ loggedInPage }) => {
		await expect(loggedInPage.locator('#wpadminbar')).toBeVisible();
	});

	test('can see admin bar on front end', async ({ loggedInPage }) => {
		await loggedInPage.goto('/');
		await expect(loggedInPage.locator('#wpadminbar')).toBeVisible();
	});

	test('can save own profile', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'profile.php');
		await loggedInPage.fill('#first_name', 'Test Name');
		await loggedInPage.click('#submit');
		await expect(loggedInPage.locator('#first_name')).toHaveValue('Test Name');
	});

	test('can change site title', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'options-general.php');
		await expect(loggedInPage.locator('#wpadminbar')).toBeVisible();
		await loggedInPage.fill('#blogname', 'Updated Title');
		await loggedInPage.click('#submit');
		await expect(loggedInPage.locator('#wp-admin-bar-site-name a').first()).toHaveText(
			'Updated Title',
		);
	});
});
