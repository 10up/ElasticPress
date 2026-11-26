import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Orders templates in native store syncs', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let targetId: string;
	let otherId: string;
	let targetUrl: string;
	let pluginStatus: string;
	let wooStatus: string;
	let settings: string;

	async function writes(id: string) {
		return JSON.parse(
			await wpCliEval(
				`echo wp_json_encode(get_blog_option(${id}, 'ep_test_orders_templates', []));`,
			),
		);
	}

	async function sync(page: Page, target: string) {
		page.setDefaultTimeout(15000);
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(target);
		const fresh = page.getByRole('checkbox', {
			name: 'Delete all data and start fresh sync',
		});

		if (await fresh.count()) {
			await fresh.check();
		}

		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 150000,
		});
		await expect(page.getByRole('tab', { name: 'Errors (0)', exact: true })).toBeVisible();
	}

	async function assertUsableTemplate(id: string, marker: string) {
		const saved = await writes(id);
		expect(saved).toHaveLength(1);
		expect(saved[0].url).toContain(`-post-${id}/template`);

		for (const field of [
			'meta.order_number.value',
			'term_suggest',
			'meta._billing_first_name.value',
		]) {
			expect(saved[0].body).toContain(field);
		}

		// The adapter captures writes; the actual generated body searches real indexed orders.
		await expect
			.poll(async () =>
				JSON.parse(
					await wpCliEval(`
			$writes = get_blog_option(${id}, 'ep_test_orders_templates', []);
			$body = str_replace('{{ep_placeholder}}', '${marker}', end($writes)['body']);
			$index = \\ElasticPress\\Indexables::factory()->get('post')->get_index_name(${id});
			$response = \\ElasticPress\\Elasticsearch::factory()->remote_request($index . '/_search', ['method' => 'POST', 'body' => $body]);
			echo wp_json_encode(array_column(json_decode(wp_remote_retrieve_body($response), true)['hits']['hits'] ?? [], '_source'));
		`),
				),
			)
			.toEqual(
				expect.arrayContaining([expect.objectContaining({ post_type: 'shop_order' })]),
			);
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		wooStatus = await wpCli('plugin list --name=woocommerce --field=status');
		settings = await wpCliEval(
			'echo base64_encode(serialize(\\ElasticPress\\Utils\\get_option("ep_feature_settings", [])));',
		);
		await wpCli('plugin activate elasticpress --network');
		await wpCli('plugin deactivate woocommerce --network', true);
		await wpCli('plugin deactivate woocommerce', true);
		await wpCli('plugin activate orders-template-capture --network');
		await wpCliEval(`
			$settings = \\ElasticPress\\Utils\\get_option('ep_feature_settings', []);
			$settings['woocommerce'] = ['active' => true, 'orders' => '1'];
			\\ElasticPress\\Utils\\update_option('ep_feature_settings', $settings);
		`);
		targetId = await wpCli(
			'site create --slug=ep-orders-target --title="Orders target" --porcelain',
		);
		otherId = await wpCli(
			'site create --slug=ep-orders-other --title="Orders other" --porcelain',
		);
		targetUrl = await wpCliEval(`echo get_home_url(${targetId});`);

		for (const [id, marker] of [
			[targetId, 'Orderfixturetarget'],
			[otherId, 'Orderfixtureother'],
		]) {
			const url = await wpCliEval(`echo get_home_url(${id});`); // eslint-disable-line no-await-in-loop
			await wpCli(`plugin activate woocommerce --url=${url}`); // eslint-disable-line no-await-in-loop
			await wpCli(`option update woocommerce_custom_orders_table_enabled no --url=${url}`); // eslint-disable-line no-await-in-loop
			// eslint-disable-next-line no-await-in-loop
			await wpCli(
				`eval '$order = new WC_Order(); $order->set_billing_first_name("${marker}"); $order->set_status("pending"); $order->save();' --url=${url}`,
			); // eslint-disable-line no-await-in-loop
		}

		await wpCliEval(`
			foreach ([get_main_site_id(), ${targetId}, ${otherId}] as $id) {
				delete_blog_option($id, 'ep_test_orders_templates');
			}
		`);
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		await wpCli('plugin deactivate orders-template-capture --network');

		for (const id of [targetId, otherId]) {
			if (id) {
				// eslint-disable-next-line no-await-in-loop
				await wpCliEval(
					`\\ElasticPress\\Indexables::factory()->get('post')->delete_index(${id});`,
				); // eslint-disable-line no-await-in-loop
				await wpCli(`site delete ${id} --yes`); // eslint-disable-line no-await-in-loop
			}
		}

		await wpCliEval(
			`\\ElasticPress\\Utils\\update_option('ep_feature_settings', unserialize(base64_decode('${settings}'))); delete_option('ep_test_orders_templates');`,
		);

		if (wooStatus === 'active-network') {
			await wpCli('plugin activate woocommerce --network');
		} else if (wooStatus === 'active') {
			await wpCli('plugin activate woocommerce');
		}

		if (pluginStatus !== 'active-network') {
			await wpCli('plugin deactivate elasticpress --network');

			if (pluginStatus === 'active') {
				await wpCli('plugin activate elasticpress');
			}
		}
	});

	test('Selected store gets a usable template when Main has no WooCommerce', async ({
		loggedInPage,
	}) => {
		expect(await wpCli('plugin list --name=woocommerce --field=status')).toBe('inactive');
		await sync(loggedInPage, targetId);
		await assertUsableTemplate(targetId, 'Orderfixturetarget');
		expect(await writes(otherId)).toEqual([]);
		expect(await writes('1')).toEqual([]);
	});

	test('Network sync provisions each store without a template on Main', async ({
		loggedInPage,
	}) => {
		await wpCliEval(`delete_blog_option(${targetId}, 'ep_test_orders_templates');`);
		await sync(loggedInPage, '');
		await assertUsableTemplate(targetId, 'Orderfixturetarget');
		await assertUsableTemplate(otherId, 'Orderfixtureother');
		expect(await writes('1')).toEqual([]);
	});

	test('Current-store CLI completion produces the same usable order fields', async () => {
		await wpCliEval(`delete_blog_option(${targetId}, 'ep_test_orders_templates');`);
		await wpCli(`elasticpress sync --indexables=post --yes --url=${targetUrl}`);
		await assertUsableTemplate(targetId, 'Orderfixturetarget');
		expect(await writes(otherId)).toHaveLength(1);
	});
});
