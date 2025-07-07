import { test, expect } from '../../fixtures';
import { wpCli, publishPost, goToAdminPage } from '../../utils';

// Parity with Cypress: Post Search Feature

test.describe('Post Search Feature', () => {
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
		await Promise.all(postsData.map((postData) => publishPost(loggedInPage, postData)));
		await loggedInPage.goto('/?s=10up+loves+elasticpress');
		await expect(loggedInPage.locator('.site-content article:nth-of-type(1) h2')).toHaveText(
			'Higher',
		);
		await expect(
			loggedInPage.locator('.site-content article h2', { hasText: 'Lower' }),
		).toBeVisible();
	});

	test('Can see newer matches showing higher', async ({ loggedInPage }) => {
		const postTitle = 'Duplicated post';

		// Create two posts with 1 month difference
		const now = new Date();
		const oneMonthAgo = new Date(now.getTime() - 2678400000);
		const yesterday = new Date(now.getTime() - 86400000);
		const formatDate = (date: Date) => date.toISOString().slice(0, 19).replace('T', ' ');

		await wpCli(
			`post create --post_title='${postTitle}' --post_content='Lorem ipsum veritas dolor' --post_author=1 --post_status='publish' --post_date='${formatDate(oneMonthAgo)}'`,
		);
		await wpCli(
			`post create --post_title='${postTitle}' --post_content='Lorem ipsum veritas dolor' --post_author=1 --post_status='publish' --post_date='${formatDate(yesterday)}'`,
		);

		await loggedInPage.waitForTimeout(2000);
		await loggedInPage.goto(`/?s=duplicated+post`);
		await expect(loggedInPage.locator('.site-content article:nth-of-type(1) h2')).toHaveText(
			postTitle,
		);
		await expect(loggedInPage.locator('.site-content article:nth-of-type(2) h2')).toHaveText(
			postTitle,
		);

		const htmlIdFirstPost = await loggedInPage
			.locator('.site-content article:nth-of-type(1)')
			.getAttribute('id');
		const htmlIdSecondPost = await loggedInPage
			.locator('.site-content article:nth-of-type(2)')
			.getAttribute('id');
		const firstPostId = Number(htmlIdFirstPost?.replace('post-', ''));
		const secondPostId = Number(htmlIdSecondPost?.replace('post-', ''));
		expect(firstPostId).toBeGreaterThan(secondPostId);
	});

	test('Can see highlighted text', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		const responsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/features*',
		);

		await loggedInPage.getByRole('button', { name: 'Core Search' }).click();
		await loggedInPage.getByRole('button', { name: 'Post Search' }).click();
		await loggedInPage.getByLabel('Weight results by date').click();
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

		await responsePromise;

		await publishPost(loggedInPage, {
			title: 'test highlight color',
			content: 'findme findme findme',
		});

		await loggedInPage.goto('/?s=findme');
		await expect(loggedInPage.locator('.ep-highlight')).toBeVisible();
	});

	test('Can not see any password protected post', async ({ loggedInPage, page }) => {
		await publishPost(loggedInPage, {
			title: 'Password Protected',
			password: 'password',
		});

		await page.goto('/');
		await expect(
			page.locator('.site-content article h2', { hasText: 'Password Protected' }),
		).not.toBeVisible();
		await page.goto('/?s=Password+Protected');
		await expect(
			page.locator('.site-content article h2', { hasText: 'Password Protected' }),
		).not.toBeVisible();
	});
});
