import { test, expect } from '../fixtures';
import {
	wpCli,
	deactivatePlugin,
	maybeDisableFeature,
	maybeEnableFeature,
	updateWeighting,
} from '../utils';

test.describe('Autosuggest Feature', () => {
	test.beforeAll(async () => {
		await wpCli('elasticpress sync --setup --yes');
		await wpCli('plugin deactivate filter-autosuggest-navigate-callback');
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await maybeDisableFeature('instant-results');
		await deactivatePlugin(loggedInPage, 'custom-headers-for-autosuggest', 'wpCli');
	});

	test('Can see autosuggest list', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('searchbox').pressSequentially('blog');
		const autosuggest = page.locator('.ep-autosuggest');
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('a Blog page');
	});

	test('Can see post in autosuggest list', async ({ page }) => {
		await page.goto('/');

		const responsePromise = page.waitForResponse((response) => {
			return response.url().includes('_search') || response.url().includes('autosuggest');
		});

		await page.getByRole('searchbox').pressSequentially('Markup: HTML Tags and Formatting');
		await responsePromise;

		const autosuggest = page.locator('.ep-autosuggest');
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Markup: HTML Tags and Formatting');

		// Test focus behavior
		await page.getByRole('button', { name: 'Search' }).focus();
		await page.getByRole('searchbox').click();
		await page.getByRole('searchbox').focus();

		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Markup: HTML Tags and Formatting');
	});

	test('Can find post by category in autosuggest list', async ({ page }) => {
		await updateWeighting({
			post: {
				'terms.category.name': {
					weight: 1,
					enabled: true,
				},
			},
		});

		await page.goto('/');
		await page.getByRole('searchbox').pressSequentially('aciform');
		const autosuggest = page.locator('.ep-autosuggest');
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Keyboard navigation');

		await updateWeighting();
	});

	test('Can click on a post in autosuggest', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('searchbox').pressSequentially('blog');

		const firstLink = page.locator('.ep-autosuggest li a').first();
		const linkHref = (await firstLink.getAttribute('href')) ?? '';
		if (linkHref) {
			await firstLink.click();
			await expect(page).toHaveURL(linkHref);
		}
	});

	test('Can see post in autosuggest list when headers are modified', async ({ page }) => {
		await wpCli('wp plugin activate custom-headers-for-autosuggest');
		await page.goto('/');

		const responsePromise = page.waitForResponse((response) => {
			return (
				(response.url().includes('_search') || response.url().includes('autosuggest')) &&
				response.request().headers()['x-elasticpress-request-id'] === 'CustomRequestId123'
			);
		});

		await page.getByRole('searchbox').pressSequentially('Markup: HTML Tags and Formatting');
		await responsePromise;

		const autosuggest = page.locator('.ep-autosuggest');
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Markup: HTML Tags and Formatting');
	});

	test('Can use autosuggest navigate callback filter', async ({ page }) => {
		await wpCli('wp plugin activate filter-autosuggest-navigate-callback');
		await page.goto('/');
		await page.getByRole('searchbox').pressSequentially('blog');
		await page.locator('.ep-autosuggest li a').first().click();
		await expect(page).toHaveURL(/.*cypress=foobar/);
	});

	test('Can select an Autosuggest suggestion even if Instant Results is active', async ({
		page,
	}) => {
		await maybeEnableFeature('instant-results');
		await page.goto('/');
		await page.getByRole('searchbox').pressSequentially('blog');
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Enter');
		await expect(page).toHaveURL(/.*blog/);
	});

	test('Can override default placeholder and confirm autosuggest works', async ({ page }) => {
		await wpCli('wp plugin activate custom-autosuggest-placeholder');
		await page.goto('/');

		// Verify autosuggest still works with the custom placeholder
		await page.getByRole('searchbox').pressSequentially('blog');
		const autosuggest = page.locator('.ep-autosuggest');
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('a Blog page');

		// Cleanup
		await wpCli('wp plugin deactivate custom-autosuggest-placeholder');
	});
});
