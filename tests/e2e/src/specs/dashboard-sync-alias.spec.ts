import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Selected-site network alias restoration', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let targetId: string;
	let otherId: string;
	let missingId: string;
	let targetIndex: string;
	let otherIndex: string;
	let pluginStatus: string;

	async function aliasState() {
		return JSON.parse(
			await wpCliEval(`
				$elastic = \\ElasticPress\\Elasticsearch::factory();
				$alias = \\ElasticPress\\Indexables::factory()->get('post')->get_network_alias();
				$members = $elastic->remote_request('_alias/' . $alias, ['method' => 'GET']);
				$search = $elastic->remote_request($alias . '/_search', [
					'method' => 'POST',
					'body' => wp_json_encode(['query' => ['match' => ['post_title' => 'Aliasfixture']], 'size' => 10]),
				]);
				echo wp_json_encode([
					'members' => array_keys(json_decode(wp_remote_retrieve_body($members), true)),
					'hits' => json_decode(wp_remote_retrieve_body($search), true)['hits']['hits'] ?? [],
					'indices' => $elastic->get_cluster_indices(),
				]);
			`),
		);
	}

	async function rebuildAndVerify(page: Page) {
		page.setDefaultTimeout(15000);
		const before = await aliasState();
		expect(before.members).toEqual(expect.arrayContaining([targetIndex, otherIndex]));
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(targetId);
		// Missing indexes trigger setup mode, which hides advanced controls and forces a fresh sync.
		const advanced = page.getByRole('button', { name: 'Advanced options' });

		if (await advanced.count()) {
			await advanced.click();
		}

		const posts = page
			.locator('fieldset')
			.filter({ hasText: 'Content to sync' })
			.getByRole('checkbox', { name: 'Posts', exact: true });

		if (await posts.count()) {
			await posts.check();
		}

		const fresh = page.getByRole('checkbox', {
			name: /^Delete (all|selected site) data and start fresh sync$/,
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
		await expect(page.locator('.ep-sync-messages')).toContainText(
			'Network alias created for posts',
		);
		const after = await aliasState();
		expect(after.members.sort()).toEqual(before.members.sort());
		expect(after.indices.find((index) => index.index === targetIndex).uuid).not.toBe(
			before.indices.find((index) => index.index === targetIndex).uuid,
		);
		expect(after.indices.find((index) => index.index === otherIndex).uuid).toBe(
			before.indices.find((index) => index.index === otherIndex).uuid,
		);

		// Search visibility follows Elasticsearch's refresh after bulk indexing.
		await expect
			.poll(async () => {
				const state = await aliasState();
				return state.hits.map((hit) => [hit._index, hit._source.post_title]).sort();
			})
			.toEqual(
				[
					[targetIndex, 'Aliasfixture target'],
					[otherIndex, 'Aliasfixture other'],
				].sort(),
			);
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		targetId = await wpCli(
			'site create --slug=ep-alias-target --title="Alias target" --porcelain',
		);
		otherId = await wpCli(
			'site create --slug=ep-alias-other --title="Alias other" --porcelain',
		);

		for (const [id, title] of [
			[targetId, 'Aliasfixture target'],
			[otherId, 'Aliasfixture other'],
		]) {
			const url = await wpCliEval(`echo get_home_url(${id});`); // eslint-disable-line no-await-in-loop
			// eslint-disable-next-line no-await-in-loop
			await wpCli(
				`post create --post_type=post --post_status=publish --post_title="${title}" --porcelain --url=${url}`,
			);
		}

		const names = JSON.parse(
			await wpCliEval(`
				$indexable = \\ElasticPress\\Indexables::factory()->get('post');
				echo wp_json_encode([$indexable->get_index_name(${targetId}), $indexable->get_index_name(${otherId})]);
			`),
		);
		[targetIndex, otherIndex] = names;
		await wpCli('elasticpress sync --network-wide --indexables=post --yes');
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		for (const id of [targetId, otherId, missingId]) {
			if (id) {
				// eslint-disable-next-line no-await-in-loop
				await wpCliEval(
					`\\ElasticPress\\Indexables::factory()->get('post')->delete_index(${id});`,
				);
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

	test('Fresh selected-site sync restores membership and preserves other search results', async ({
		loggedInPage,
	}) => {
		await rebuildAndVerify(loggedInPage);
	});

	test('An unrelated eligible site without an index does not block selected-site alias repair', async ({
		loggedInPage,
	}) => {
		missingId = await wpCli(
			'site create --slug=ep-alias-missing --title="Unindexed alias site" --porcelain',
		);
		await wpCliEval(
			`\\ElasticPress\\Indexables::factory()->get('post')->delete_index(${missingId});`,
		);
		await rebuildAndVerify(loggedInPage);
		expect(
			await wpCliEval(
				`echo (int) \\ElasticPress\\Indexables::factory()->get('post')->index_exists(${missingId});`,
			),
		).toBe('0');
	});
});
