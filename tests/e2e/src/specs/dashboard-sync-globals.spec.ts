import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Network shared indexable scope', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 120000 });
	let siteId: string;
	let pluginStatus: string;
	let globalIndex: string;
	let postIndex: string;

	async function indexState(indexName: string) {
		const indices = JSON.parse(
			await wpCliEval(
				'echo wp_json_encode(\\ElasticPress\\Elasticsearch::factory()->get_cluster_indices());',
			),
		);
		const index = indices.find((item) => item.index === indexName);
		expect(index).toBeDefined();
		return index;
	}

	async function completeSync(page: Page) {
		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 90000,
		});
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		await wpCli('plugin activate global-indexable --network');
		siteId = await wpCli(
			'site create --slug=ep-sync-globals --title="Shared scope target" --porcelain',
		);
		globalIndex = await wpCliEval(
			'echo \\ElasticPress\\Indexables::factory()->get("global")->get_index_name();',
		);
		postIndex = await wpCliEval(
			`echo \\ElasticPress\\Indexables::factory()->get("post")->get_index_name(${siteId});`,
		);
		await wpCli('elasticpress sync --network-wide --indexables=post,global --yes');
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		if (globalIndex) {
			await wpCli(`elasticpress delete-index --index-name=${globalIndex} --yes`);
		}

		await wpCli('plugin deactivate global-indexable --network');

		if (siteId) {
			await wpCli(`site delete ${siteId} --yes`);
		}

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Excludes shared data from a selected-site sync across pause and reload', async ({
		loggedInPage,
	}) => {
		const before = await indexState(globalIndex);
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await loggedInPage.getByRole('button', { name: 'Advanced options' }).click();
		const shared = loggedInPage.getByRole('checkbox', { name: 'Global', exact: true });
		await expect(shared).toBeDisabled();
		await expect(shared).not.toBeChecked();
		await expect(
			loggedInPage.getByText(
				'Shared network content is synced only when All eligible sites is selected.',
			),
		).toBeVisible();
		await loggedInPage.getByRole('button', { name: 'Start sync', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Pause sync', exact: true }).click();
		await openSyncLog(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-messages')).toContainText('Sync paused');
		await loggedInPage.reload();
		await loggedInPage.getByRole('button', { name: 'Resume sync', exact: true }).click();
		await openSyncLog(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 90000,
		});
		await expect(loggedInPage.locator('.ep-sync-messages')).not.toContainText('(globally)');
		expect((await indexState(globalIndex)).uuid).toBe(before.uuid);
	});

	test('A selected-site fresh rebuild preserves the shared index', async ({ loggedInPage }) => {
		const sharedBefore = await indexState(globalIndex);
		const postBefore = await indexState(postIndex);
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await loggedInPage
			.getByRole('checkbox', { name: 'Delete all data and start fresh sync' })
			.check();
		await completeSync(loggedInPage);
		const sharedAfter = await indexState(globalIndex);
		expect(sharedAfter.uuid).toBe(sharedBefore.uuid);
		expect(sharedAfter['docs.count']).toBe(sharedBefore['docs.count']);
		expect((await indexState(postIndex)).uuid).not.toBe(postBefore.uuid);
		await expect(loggedInPage.locator('.ep-sync-messages')).not.toContainText('(globally)');
	});

	test('Switching a shared-only selection to one site requires a local choice', async ({
		loggedInPage,
	}) => {
		const sharedBefore = await indexState(globalIndex);
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('button', { name: 'Advanced options' }).click();
		await loggedInPage.getByRole('checkbox', { name: 'Global', exact: true }).check();
		await loggedInPage.getByRole('combobox', { name: 'Sites to sync' }).selectOption(siteId);
		await expect(
			loggedInPage.getByRole('button', { name: 'Start sync', exact: true }),
		).toBeDisabled();
		await expect(
			loggedInPage
				.locator('#ep-sync')
				.getByText(
					'Select site-specific content to sync, or select All eligible sites to sync shared network content.',
				),
		).toBeVisible();
		await loggedInPage
			.locator('fieldset')
			.filter({ hasText: 'Content to sync' })
			.getByRole('checkbox', { name: 'Posts', exact: true })
			.check();
		await completeSync(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-messages')).toContainText(
			`Number of posts indexed on site ${siteId}:`,
		);
		await expect(loggedInPage.locator('.ep-sync-messages')).not.toContainText('(globally)');
		expect((await indexState(globalIndex)).uuid).toBe(sharedBefore.uuid);
	});

	test('All eligible sites can rebuild shared data without rebuilding posts', async ({
		loggedInPage,
	}) => {
		const sharedBefore = await indexState(globalIndex);
		const postBefore = await indexState(postIndex);
		await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-sync');
		await loggedInPage.getByRole('button', { name: 'Advanced options' }).click();
		await loggedInPage.getByRole('checkbox', { name: 'Global', exact: true }).check();
		await loggedInPage
			.getByRole('checkbox', { name: 'Delete all data and start fresh sync' })
			.check();
		await completeSync(loggedInPage);
		await expect(loggedInPage.locator('.ep-sync-messages')).toContainText(
			'Indexing global (globally)',
		);
		await expect(loggedInPage.locator('.ep-sync-messages')).not.toContainText('Indexing posts');
		expect((await indexState(globalIndex)).uuid).not.toBe(sharedBefore.uuid);
		expect((await indexState(postIndex)).uuid).toBe(postBefore.uuid);
	});
});
