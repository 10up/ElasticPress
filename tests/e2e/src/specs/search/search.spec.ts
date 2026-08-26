import { test, expect } from '../../fixtures.js';
import {
	wpCli,
	wpCliEval,
	publishPost,
	goToAdminPage,
	setDefaultFeatureSettings,
	refreshIndex,
} from '../../utils.js';

// Parity with Cypress: Post Search Feature

test.describe('Post Search Feature', { tag: '@group1' }, () => {
	test.beforeAll(async () => {
		await wpCli('elasticpress sync --setup --yes');
	});

	test('Can use Elasticsearch for the default WP search', async ({ loggedInPage }) => {
		await loggedInPage.goto('/?s=test');
		const debugText = await loggedInPage
			.locator('#debug-menu-target-EP_Debug_Bar_ElasticPress')
			.textContent();
		expect(debugText).toContain('Query Response Code: HTTP 200');
		expect(debugText).not.toContain('Query Response Code: HTTP 4');
		expect(debugText).not.toContain('Query Response Code: HTTP 5');
	});

	test('Can see exact matches showing higher', async ({ loggedInPage }) => {
		const postsData = [
			{ title: 'Higher', content: '10up loves elasticpress' },
			{ title: 'Lower', content: 'elasticpress loves 10up' },
		];
		for await (const postData of postsData) {
			await publishPost(loggedInPage, postData);
		}
		await loggedInPage.waitForTimeout(2000);
		await loggedInPage.goto('/?s=10up+loves+elasticpress');
		await expect(loggedInPage.locator('.site-content article:nth-of-type(1) h2')).toHaveText(
			'Higher',
		);
		await expect(
			loggedInPage.locator('.site-content article h2', { hasText: 'Lower' }),
		).toBeVisible();
	});

	test('Can see newer matches showing higher', async ({ loggedInPage }) => {
		const unique = Date.now();
		const olderTitle = `Duplicated post older ${unique}`;
		const newerTitle = `Duplicated post newer ${unique}`;

		const now = new Date();
		const oneMonthAgo = new Date(now.getTime() - 2678400000);
		const yesterday = new Date(now.getTime() - 86400000);
		const formatGmt = (date: Date) => date.toISOString().slice(0, 19).replace('T', ' ');

		await wpCliEval(`
			wp_insert_post(
				[
					'post_title'    => '${olderTitle}',
					'post_content'  => 'Lorem ipsum veritas dolor',
					'post_author'   => 1,
					'post_status'   => 'publish',
					'post_date'     => '${formatGmt(oneMonthAgo)}',
					'post_date_gmt' => '${formatGmt(oneMonthAgo)}',
				]
			);
			wp_insert_post(
				[
					'post_title'    => '${newerTitle}',
					'post_content'  => 'Lorem ipsum veritas dolor',
					'post_author'   => 1,
					'post_status'   => 'publish',
					'post_date'     => '${formatGmt(yesterday)}',
					'post_date_gmt' => '${formatGmt(yesterday)}',
				]
			);
		`);
		await refreshIndex('post');

		await loggedInPage.goto(`/?s=${encodeURIComponent(`Duplicated post ${unique}`)}`);
		await expect(loggedInPage.locator('.site-content article:nth-of-type(1) h2')).toHaveText(
			newerTitle,
		);
		await expect(loggedInPage.locator('.site-content article:nth-of-type(2) h2')).toHaveText(
			olderTitle,
		);
	});

	test('Can see highlighted text', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		const responsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/features*',
		);

		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
		await loggedInPage
			.getByRole('radio', { name: 'Weight results by date', exact: true })
			.click();
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

		await responsePromise;

		await publishPost(loggedInPage, {
			title: 'test highlight color',
			content: 'findme findme findme',
		});

		await loggedInPage.goto('/?s=findme');
		await expect(loggedInPage.locator('.ep-highlight').first()).toBeVisible();
	});

	test('Can not see any password protected post', async ({ loggedInPage, page }) => {
		// Reset features. If the Facets/Filters are enabled, the password protected post will not be visible in the homepage.
		await setDefaultFeatureSettings();

		const postTitle = `Password Protected ${Date.now()}`;
		await publishPost(loggedInPage, {
			title: postTitle,
			password: 'password',
		});

		await page.goto('/');
		await expect(
			page.locator('.site-content article h2', { hasText: postTitle }),
		).not.toBeVisible();

		await page.goto('/?s=Password+Protected');
		await expect(
			page.locator('.site-content article h2', { hasText: postTitle }),
		).not.toBeVisible();
	});
});
