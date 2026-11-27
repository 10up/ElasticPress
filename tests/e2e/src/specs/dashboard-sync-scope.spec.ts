import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Sync scope and rebuild mode', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let targetId: string;
	let pluginStatus: string;

	async function checkReport(page: Page, fresh: boolean, network = false) {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-status-report');
		const report = page
			.locator('.ep-status-report')
			.filter({ has: page.getByRole('heading', { name: 'Last Sync', exact: true }) });
		await report.getByRole('button').click();
		await expect(report).toContainText('Scope target');
		await expect(report).toContainText(`ID ${targetId}`);
		await expect(report).toContainText(fresh ? 'Fresh rebuild' : 'Ordinary sync');
		await expect(report).toContainText('Target scope');
		await expect(report).toContainText('Sync mode');

		if (network) {
			await expect(report).toContainText('ID 1');
		}
	}

	async function sync(page: Page, fresh = false, network = false) {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page
			.getByRole('combobox', { name: 'Sites to sync' })
			.selectOption(network ? '' : targetId);

		if (fresh) {
			await page.getByRole('button', { name: 'Advanced options' }).click();
			await page
				.getByRole('checkbox', { name: 'Delete all data and start fresh sync' })
				.check();
		}

		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 150000,
		});
		await expect(page.getByRole('tab', { name: 'Errors (0)', exact: true })).toBeVisible();
		await page.reload();
		const latest = page.locator('.ep-sync-history > li').first();
		await expect(latest).toContainText('Scope target');
		await expect(latest).toContainText(`ID ${targetId}`);
		await expect(latest).toContainText(
			`Sync mode: ${fresh ? 'Fresh rebuild' : 'Ordinary sync'}`,
		);

		if (network) {
			await expect(latest).toContainText('ID 1');
		} else {
			await expect(latest).not.toContainText('Shared network content:');
		}

		await checkReport(page, fresh, network);
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		targetId = await wpCli(
			'site create --slug=ep-scope-target --title="Scope target" --porcelain',
		);
		await wpCli('elasticpress sync --network-wide --yes');
	});

	test.beforeEach(async ({ loggedInPage: page }) => {
		await page.waitForURL('**/wp-admin/**');
		page.setDefaultTimeout(15000);
	});

	test.afterAll(async () => {
		await wpCli('elasticpress clear-sync');
		await wpCliEval(
			`\\ElasticPress\\Indexables::factory()->get('post')->delete_index(${targetId});`,
		);
		await wpCli(`site delete ${targetId} --yes`);

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Ordinary selected-site scope persists in History and Last Sync', async ({
		loggedInPage: page,
	}) => {
		await sync(page);
	});

	test('Fresh rebuilding changes mode without changing the selected target', async ({
		loggedInPage: page,
	}) => {
		await sync(page, true);
	});

	test('Ordinary network work shows every target independently of mode', async ({
		loggedInPage: page,
	}) => {
		await sync(page, false, true);
	});

	test('Legacy history retains known rebuild mode without inventing scope', async ({
		loggedInPage: page,
	}) => {
		await wpCliEval(`
			$history = \\ElasticPress\\Utils\\get_option('ep_sync_history');
			unset($history[0]['scope']);
			$history[0]['is_full_sync'] = true;
			\\ElasticPress\\Utils\\update_option('ep_sync_history', $history);
		`);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		const latest = page.locator('.ep-sync-history > li').first();
		await expect(latest).toContainText('Target scope: Not recorded');
		await expect(latest).toContainText('Sync mode: Fresh rebuild');
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-status-report');
		const report = page
			.locator('.ep-status-report')
			.filter({ has: page.getByRole('heading', { name: 'Last Sync', exact: true }) });
		await report.getByRole('button').click();
		await expect(report).toContainText('Not recorded');
		await expect(report).toContainText('Fresh rebuild');
	});
});
