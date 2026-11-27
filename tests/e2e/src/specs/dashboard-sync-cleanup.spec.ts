import { test, expect, Page } from '../fixtures.js';
import { goToAdminPage, openSyncLog, wpCli, wpCliEval } from '../utils.js';

test.describe('Inactive network index cleanup', { tag: '@group2' }, () => {
	test.describe.configure({ mode: 'serial', timeout: 180000 });
	let targetId: string;
	let otherId: string;
	let pluginStatus: string;
	let commentsWereActive: boolean;
	let commentNames: string[];
	let targetComment: string;
	let otherComment: string;
	let mainPost: string;
	let targetPost: string;
	let otherPost: string;

	async function indices() {
		return JSON.parse(
			await wpCliEval(
				'echo wp_json_encode(\\ElasticPress\\Elasticsearch::factory()->get_cluster_indices());',
			),
		);
	}

	async function sync(page: Page, target: string, fresh: boolean) {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress-sync');
		await page.getByRole('combobox', { name: 'Sites to sync' }).selectOption(target);

		if (fresh) {
			await page
				.getByRole('checkbox', {
					name: /^Delete (all|selected site) data and start fresh sync$/,
				})
				.check();
		}

		await page.getByRole('button', { name: 'Start sync', exact: true }).click();
		await openSyncLog(page);
		await expect(page.locator('.ep-sync-progress strong')).toHaveText('Sync complete', {
			timeout: 150000,
		});
		await expect(page.getByRole('tab', { name: 'Errors (0)', exact: true })).toBeVisible();
	}

	test.beforeAll(async () => {
		pluginStatus = await wpCli('plugin list --name=elasticpress --field=status');
		await wpCli('plugin activate elasticpress --network');
		commentsWereActive =
			(await wpCliEval(
				'echo (int) \\ElasticPress\\Features::factory()->get_registered_feature("comments")->is_active();',
			)) === '1';
		await wpCli('elasticpress activate-feature comments', true);
		targetId = await wpCli(
			'site create --slug=ep-cleanup-target --title="Cleanup target" --porcelain',
		);
		otherId = await wpCli(
			'site create --slug=ep-cleanup-other --title="Cleanup other" --porcelain',
		);
		const names = JSON.parse(
			await wpCliEval(`
				$post_indexable = \\ElasticPress\\Indexables::factory()->get('post');
				$comment_indexable = \\ElasticPress\\Indexables::factory()->get('comment');
				echo wp_json_encode([
					'comments' => array_map(static fn($site) => $comment_indexable->get_index_name($site['blog_id']), \\ElasticPress\\Utils\\get_sync_sites()),
					'targetComment' => $comment_indexable->get_index_name(${targetId}),
					'otherComment' => $comment_indexable->get_index_name(${otherId}),
					'mainPost' => $post_indexable->get_index_name(),
					'targetPost' => $post_indexable->get_index_name(${targetId}),
					'otherPost' => $post_indexable->get_index_name(${otherId}),
				]);
			`),
		);
		commentNames = names.comments;
		({ targetComment, otherComment, mainPost, targetPost, otherPost } = names);
		await wpCli('elasticpress sync --network-wide --indexables=post,comment --yes');
	});

	test.afterEach(async () => {
		await wpCli('elasticpress clear-sync');
	});

	test.afterAll(async () => {
		const featureCommand = commentsWereActive ? 'activate-feature' : 'deactivate-feature';
		await wpCli(`elasticpress ${featureCommand} comments`, true);

		for (const id of [targetId, otherId]) {
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

	test('An ordinary selected-site sync retains inactive indexes', async ({
		loggedInPage: page,
	}) => {
		await goToAdminPage(page, 'network/admin.php?page=elasticpress#/comments');
		await page.getByRole('checkbox', { name: 'Enable', exact: true }).uncheck();
		await page.getByRole('button', { name: 'Save changes', exact: true }).click();
		await expect(
			page
				.locator('.components-snackbar__content')
				.filter({ hasText: 'Feature settings saved.' }),
		).toBeVisible();
		await page.reload();
		await expect(page.getByRole('checkbox', { name: 'Enable', exact: true })).not.toBeChecked();
		const before = await indices();

		for (const name of commentNames) {
			expect(before.find((index) => index.index === name)).toBeDefined();
		}

		await sync(page, targetId, false);
		const after = await indices();

		for (const name of commentNames) {
			expect(after.find((index) => index.index === name)?.uuid).toBe(
				before.find((index) => index.index === name).uuid,
			);
		}
	});

	test('A selected fresh sync deletes only its inactive index', async ({
		loggedInPage: page,
	}) => {
		const before = await indices();
		await sync(page, targetId, true);
		const after = await indices();
		expect(after.find((index) => index.index === targetComment)).toBeUndefined();
		await expect(page.locator('.ep-sync-messages')).toContainText(
			`Index ${targetComment} deleted`,
		);
		await expect(page.locator('.ep-sync-messages')).not.toContainText(
			`Index ${otherComment} deleted`,
		);

		for (const name of [
			...commentNames.filter((name) => name !== targetComment),
			mainPost,
			otherPost,
		]) {
			expect(after.find((index) => index.index === name)?.uuid).toBe(
				before.find((index) => index.index === name).uuid,
			);
		}

		expect(after.find((index) => index.index === targetPost)?.uuid).not.toBe(
			before.find((index) => index.index === targetPost).uuid,
		);
		await expect
			.poll(async () => {
				const refreshed = await indices();
				return Number(
					refreshed.find((index) => index.index === targetPost)?.['docs.count'],
				);
			})
			.toBeGreaterThan(0);
	});

	test('An all-sites fresh sync removes remaining inactive indexes and preserves active content', async ({
		loggedInPage: page,
	}) => {
		const before = await indices();
		await sync(page, '', true);
		const after = await indices();

		for (const name of commentNames) {
			expect(after.find((index) => index.index === name)).toBeUndefined();
		}

		const activeNames = [mainPost, targetPost, otherPost];
		const counts = activeNames.map(
			(name) => before.find((index) => index.index === name)['docs.count'],
		);

		for (const count of counts) {
			expect(Number(count)).toBeGreaterThan(0);
		}

		// Elasticsearch exposes refreshed document counts asynchronously after bulk indexing.
		await expect
			.poll(async () => {
				const refreshed = await indices();
				return activeNames.map(
					(name) => refreshed.find((index) => index.index === name)?.['docs.count'],
				);
			})
			.toEqual(counts);
	});
});
