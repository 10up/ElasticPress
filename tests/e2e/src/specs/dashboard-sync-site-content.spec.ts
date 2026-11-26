import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli } from '../utils.js';

test.describe('Native subsite sync content', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let siteId: string;
	let otherId: string;
	let siteUrl: string;
	let bookId: string;
	let privateId: string;
	let pluginStatus: string;

	async function document(id: string) {
		return JSON.parse(
			await wpCli(
				`eval 'echo wp_json_encode(\\ElasticPress\\Indexables::factory()->get("post")->get(${id}));' --url=${siteUrl}`,
			),
		);
	}

	async function rebuild(page: Page, target: string) {
		page.setDefaultTimeout(15000);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(target);
		await page.getByRole('button', { name: 'Advanced options' }).click();
		const posts = page
			.locator('fieldset')
			.filter({ hasText: 'Content to sync' })
			.getByRole('checkbox', { name: 'Posts', exact: true });

		if (await posts.count()) {
			await posts.check();
		}

		await page.getByRole('checkbox', { name: 'Delete all data and start fresh sync' }).check();
		const prepared = page.waitForResponse(async (response) => {
			const isUnrelatedRequest =
				!response.url().includes('/elasticpress/v1/sync') ||
				response.request().method() !== 'POST';

			if (isUnrelatedRequest) {
				return false;
			}

			const body = await response.json();
			return body.data?.index_meta?.sync_stack?.some(
				(item) => item.blog_id === Number(siteId) && item.found_items === 3,
			);
		});
		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await prepared;
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 150000,
		});
		await expect(page.locator('.ep-sync-messages')).toContainText(
			`Number of posts indexed on site ${siteId}: 3`,
		);
		const book = await document(bookId);
		expect(book.post_type).toBe('ep_site_book');
		expect(book.terms.ep_book_genre[0].name).toBe('Native genre');
		expect(await document(privateId)).toBe(false);
		await page.goto(`${siteUrl}?s=Nativebookfixture`);
		await expect(
			page.getByRole('link', { name: 'Nativebookfixture', exact: true }).first(),
		).toBeVisible();
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		siteId = await wpCli(
			'site create --slug=ep-native-content --title="Native content target" --porcelain',
		);
		otherId = await wpCli(
			'site create --slug=ep-native-other --title="Other content target" --porcelain',
		);
		siteUrl = await wpCli(`eval 'echo get_home_url(${siteId});'`);
		await wpCli(`plugin activate site-sync-content --url=${siteUrl}`);
		bookId = await wpCli(
			`post create --post_type=ep_site_book --post_status=publish --post_title=Nativebookfixture --porcelain --url=${siteUrl}`,
		);
		privateId = await wpCli(
			`post create --post_type=ep_private_book --post_status=publish --post_title=Privatebookfixture --porcelain --url=${siteUrl}`,
		);
		await wpCli(
			`term create ep_book_genre 'Native genre' --slug=native-genre --url=${siteUrl}`,
		);
		await wpCli(
			`post term add ${bookId} ep_book_genre native-genre --by=slug --url=${siteUrl}`,
		);
		await wpCli('elasticpress sync --network-wide --indexables=post --yes');
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		for (const id of [siteId, otherId]) {
			if (id) {
				await wpCli(`site delete ${id} --yes`); // eslint-disable-line no-await-in-loop
			}
		}

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Loads native choices and resets stale scope filters', async ({ loggedInPage: page }) => {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('button', { name: 'Advanced options' }).click();
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await expect(page.getByRole('checkbox', { name: 'Site Books', exact: true })).toBeVisible();
		await expect(
			page.getByRole('checkbox', { name: 'Private Books', exact: true }),
		).toHaveCount(0);
		await page.getByRole('checkbox', { name: 'Site Books', exact: true }).check();
		await page.getByRole('spinbutton', { name: 'Skip objects' }).fill('1');
		await page.getByRole('radio', { name: 'Specific IDs' }).check();
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(otherId);
		await expect(page.getByRole('checkbox', { name: 'Site Books', exact: true })).toHaveCount(
			0,
		);
		await expect(page.getByRole('spinbutton', { name: 'Skip objects' })).toHaveValue('0');
		await expect(page.getByRole('radio', { name: 'All objects' })).toBeChecked();
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await expect(page.getByRole('checkbox', { name: 'Site Books', exact: true })).toBeChecked({
			indeterminate: true,
		});
	});

	test('Selected-site fresh sync preserves native content and taxonomy', async ({
		loggedInPage,
	}) => {
		await rebuild(loggedInPage, siteId);
	});

	test('Restores explicit native filters when a paused sync is reloaded', async ({
		loggedInPage: page,
	}) => {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await page.getByRole('button', { name: 'Advanced options' }).click();
		await page.getByRole('checkbox', { name: 'Site Books', exact: true }).check();
		await page.getByRole('checkbox', { name: 'Delete all data and start fresh sync' }).check();
		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await page.getByRole('button', { name: 'Pause sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-messages')).toContainText('Sync paused');
		await page.reload();
		await page.getByRole('button', { name: 'Advanced options' }).click();
		await expect(page.getByRole('checkbox', { name: 'Site Books', exact: true })).toBeChecked();
		await expect(
			page.getByRole('checkbox', { name: 'Delete all data and start fresh sync' }),
		).toBeChecked();
		await page.getByRole('button', { name: 'Resume sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 90000,
		});
		await expect(page.locator('.ep-sync-messages')).toContainText(
			`Number of posts indexed on site ${siteId}: 1`,
		);
		expect((await document(bookId)).post_type).toBe('ep_site_book');
	});

	test('All-sites fresh sync preserves native content and taxonomy', async ({ loggedInPage }) => {
		await rebuild(loggedInPage, '');
	});
});
