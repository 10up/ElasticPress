import { test, expect, Page } from '../fixtures.js';
import {
	wpCli,
	deactivatePlugin,
	maybeDisableFeature,
	maybeEnableFeature,
	updateWeighting,
	wpCliEval,
	goToAdminPage,
	login,
	isEpIo,
} from '../utils.js';

/**
 * Visible site search, not the admin bar or a collapsed header field.
 * Autosuggest adds one hidden `.ep-autosuggest` per matching input, so
 * `.first()` is often the unused header list.
 *
 * @param page Playwright page object
 * @returns Locator for the visible site search field
 */
const frontendSearch = (page: Page) => page.locator('#page').getByRole('searchbox').first();

const frontendAutosuggest = (page: Page) =>
	frontendSearch(page)
		.locator('xpath=ancestor::*[contains(@class, "ep-autosuggest-container")][1]')
		.locator('.ep-autosuggest');

test.describe('Autosuggest Feature', { tag: '@group2' }, () => {
	test.beforeAll(async ({ browser }) => {
		await wpCliEval(`
			WP_CLI::runcommand( "plugin activate cpt-and-custom-tax", [ 'return' => 'all', 'exit_error' => false ] );
			WP_CLI::runcommand( 'elasticpress sync --setup --yes' );
			WP_CLI::runcommand( 'plugin deactivate filter-autosuggest-navigate-callback', [ 'return' => 'all', 'exit_error' => false ] );
			WP_CLI::runcommand( 'widget add search sidebar-1', [ 'return' => true, 'exit_error' => false ] );
		`);

		if (isEpIo()) {
			const loggedInPage = await browser.newPage();
			await login(loggedInPage);
			await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-status-report');
			await loggedInPage
				.getByRole('button')
				.getByText('Allowed Autosuggest Parameters')
				.click();
			const autosuggestLink = loggedInPage.getByRole('link').getByText('this URL');
			const autosuggestUrl = (await autosuggestLink.getAttribute('href')) ?? '';
			await loggedInPage.goto(autosuggestUrl);
			await loggedInPage.close();
		}
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await maybeDisableFeature('instant-results');
		await deactivatePlugin(loggedInPage, 'custom-headers-for-autosuggest', 'wpCli');
	});

	test('Can see autosuggest list', async ({ page }) => {
		await page.goto('/');
		const responsePromise = page.waitForResponse((response) => {
			return response.url().includes('_search') || response.url().includes('autosuggest');
		});
		await frontendSearch(page).pressSequentially('a Blog page');
		await responsePromise;
		const autosuggest = frontendAutosuggest(page);
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('a Blog page');
	});

	test('Can see post in autosuggest list', async ({ page }) => {
		await page.goto('/');

		const responsePromise = page.waitForResponse((response) => {
			return response.url().includes('_search') || response.url().includes('autosuggest');
		});

		await frontendSearch(page).pressSequentially('Markup: HTML Tags and Formatting');
		await responsePromise;

		const autosuggest = frontendAutosuggest(page);
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Markup: HTML Tags and Formatting');

		// Test focus behavior
		await page.getByRole('button', { name: 'Search' }).first().focus();
		await frontendSearch(page).click();
		await frontendSearch(page).focus();

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
		await frontendSearch(page).pressSequentially('aciform');
		const autosuggest = frontendAutosuggest(page);
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Keyboard navigation');

		await updateWeighting();
	});

	test('Can click on a post in autosuggest', async ({ page }) => {
		await page.goto('/');
		await frontendSearch(page).pressSequentially('blog');

		const firstLink = frontendAutosuggest(page).locator('li a').first();
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

		await frontendSearch(page).pressSequentially('Markup: HTML Tags and Formatting');
		await responsePromise;

		const autosuggest = frontendAutosuggest(page);
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('Markup: HTML Tags and Formatting');
	});

	test('Can use autosuggest navigate callback filter', async ({ page }) => {
		await wpCli('wp plugin activate filter-autosuggest-navigate-callback');
		await page.goto('/');
		await frontendSearch(page).pressSequentially('blog');
		await frontendAutosuggest(page).locator('li a').first().click();
		await expect(page).toHaveURL(/.*cypress=foobar/);
	});

	test('Can select an Autosuggest suggestion even if Instant Results is active', async ({
		page,
	}) => {
		await maybeEnableFeature('instant-results');
		await page.goto('/');
		await frontendSearch(page).pressSequentially('blog');
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Enter');
		await expect(page).toHaveURL(/.*blog/);
	});

	test('Can override default placeholder and confirm autosuggest works', async ({ page }) => {
		await wpCli('wp plugin activate custom-autosuggest-placeholder');
		await page.goto('/');

		// Verify autosuggest still works with the custom placeholder
		const responsePromise = page.waitForResponse((response) => {
			return response.url().includes('_search') || response.url().includes('autosuggest');
		});
		await frontendSearch(page).pressSequentially('a Blog page');
		await responsePromise;
		const autosuggest = frontendAutosuggest(page);
		await expect(autosuggest).toBeVisible();
		await expect(autosuggest).toContainText('a Blog page');

		// Cleanup
		await wpCli('wp plugin deactivate custom-autosuggest-placeholder');
	});
});
