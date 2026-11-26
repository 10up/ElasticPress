import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Sync requirement fulfillment', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let targetId: string;
	let pluginStatus: string;
	let savedOptions: string;
	const optionNames = [
		'ep_last_sync',
		'ep_need_upgrade_sync',
		'ep_feature_auto_activated_sync',
		'ep_hide_upgrade_sync_notice',
		'ep_hide_auto_activate_sync_notice',
		'ep_skip_install',
		'ep_feature_settings',
		'ep_feature_settings_draft',
	];

	async function requirements() {
		return JSON.parse(
			await wpCliEval(`echo wp_json_encode([
				'last' => \\ElasticPress\\Utils\\get_option('ep_last_sync', false),
				'upgrade' => (bool) \\ElasticPress\\Utils\\get_option('ep_need_upgrade_sync', false),
				'feature' => \\ElasticPress\\Utils\\get_option('ep_feature_auto_activated_sync', false),
			]);`),
		);
	}

	async function seedNotices() {
		await wpCliEval(`
			\\ElasticPress\\Utils\\update_option('ep_last_sync', 123);
			\\ElasticPress\\Utils\\update_option('ep_need_upgrade_sync', true);
			\\ElasticPress\\Utils\\update_option('ep_feature_auto_activated_sync', 'woocommerce');
			\\ElasticPress\\Utils\\delete_option('ep_hide_upgrade_sync_notice');
			\\ElasticPress\\Utils\\delete_option('ep_hide_auto_activate_sync_notice');
			\\ElasticPress\\Utils\\delete_option('ep_feature_settings_draft');
		`);
	}

	async function start(page: Page, target = '', fresh = false) {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(target);

		if (fresh) {
			await page.getByRole('button', { name: 'Advanced options' }).click();
			await page
				.getByRole('checkbox', { name: 'Delete all data and start fresh sync' })
				.check();
		}

		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
	}

	async function complete(page: Page) {
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 150000,
		});
		await expect(page.getByRole('tab', { name: 'Errors (0)', exact: true })).toBeVisible();
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		savedOptions = await wpCliEval(`
			$values = [];
			foreach (json_decode('${JSON.stringify(optionNames)}') as $name) {
				$values[$name] = \\ElasticPress\\Utils\\get_option($name, null);
			}
			echo base64_encode(serialize($values));
		`);
		targetId = await wpCli(
			'site create --slug=ep-bookkeeping-target --title="Bookkeeping target" --porcelain',
		);
		await wpCli('elasticpress sync --network-wide --yes');
	});

	test.beforeEach(async ({ loggedInPage: page }) => {
		await page.waitForURL('**/wp-admin/**');
		page.setDefaultTimeout(15000);
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		await wpCliEval(`
			foreach (unserialize(base64_decode('${savedOptions}')) as $name => $value) {
				if (null === $value) {
					\\ElasticPress\\Utils\\delete_option($name);
				} else {
					\\ElasticPress\\Utils\\update_option($name, $value);
				}
			}
			\\ElasticPress\\Indexables::factory()->get('post')->delete_index(${targetId});
		`);
		await wpCli(`site delete ${targetId} --yes`);

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Paused and stopped work retains the upgrade notice and saved requirements', async ({
		loggedInPage: page,
	}) => {
		await seedNotices();
		await page.route('**/elasticpress/v1/sync**', async (route) => {
			await new Promise((resolve) => {
				setTimeout(resolve, 300);
			});
			await route.continue();
		});
		await start(page);
		await expect
			.poll(async () =>
				wpCliEval('echo (int) (false !== \\ElasticPress\\Utils\\get_indexing_status());'),
			)
			.toBe('1');
		await page.getByRole('button', { name: 'Pause sync' }).click();
		await expect(page.getByRole('button', { name: 'Resume sync' })).toBeVisible();
		expect(await requirements()).toEqual({
			last: '123',
			upgrade: true,
			feature: 'woocommerce',
		});
		await page.getByRole('button', { name: 'Stop sync' }).click();
		await expect(page.getByRole('button', { name: 'Start sync', exact: true })).toBeVisible();
		await goToAdminPage(page, 'network/admin.php?page=elasticpress');
		await expect(
			page.getByText('The new version of ElasticPress requires that you', { exact: false }),
		).toBeVisible();
		expect(await requirements()).toEqual({
			last: '123',
			upgrade: true,
			feature: 'woocommerce',
		});
	});

	test('Completed selected-site rebuilding cannot fulfill network requirements', async ({
		loggedInPage: page,
	}) => {
		await seedNotices();
		await start(page, targetId, true);
		await complete(page);
		expect(await requirements()).toEqual({
			last: '123',
			upgrade: true,
			feature: 'woocommerce',
		});
	});

	test('Ordinary complete coverage fulfills applied features but not an upgrade', async ({
		loggedInPage: page,
	}) => {
		await seedNotices();
		await start(page);
		await complete(page);
		const state = await requirements();
		expect(state.feature).toBe(false);
		expect(state.upgrade).toBe(true);
		expect(Number(state.last)).toBeGreaterThan(123);
	});

	test('Setup remains incomplete when paused, can reload and resume, then completes with a fresh run', async ({
		loggedInPage: page,
	}) => {
		await wpCliEval(
			"\\ElasticPress\\Utils\\delete_option('ep_last_sync'); \\ElasticPress\\Utils\\delete_option('ep_skip_install');",
		);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress');
		await page.route('**/elasticpress/v1/sync**', async (route) => {
			await new Promise((resolve) => {
				setTimeout(resolve, 300);
			});
			await route.continue();
		});
		await page.getByRole('button', { name: 'Save Features' }).click();
		await page.getByRole('link', { name: 'Index Your Content' }).click();
		await expect
			.poll(async () =>
				wpCliEval('echo (int) (false !== \\ElasticPress\\Utils\\get_indexing_status());'),
			)
			.toBe('1');
		await page.getByRole('button', { name: 'Pause sync' }).click();
		expect((await requirements()).last).toBe(false);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await expect(page.getByRole('button', { name: 'Resume sync' })).toBeVisible();
		await page.getByRole('button', { name: 'Resume sync' }).click();
		await complete(page);
		const state = await requirements();
		expect(state.last).toBeTruthy();
		expect(state.upgrade).toBe(false);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress');
		await expect(page.getByRole('heading', { name: 'Features', exact: true })).toBeVisible();
	});
});
