import { test, expect } from '../fixtures';
import { activatePlugin, deactivatePlugin, goToAdminPage, wpCli, wpCliEval } from '../utils';

const indexNames = process.env?.EP_INDEX_NAMES || [];

test.describe('WP-CLI Commands', () => {
	let indexAllSitesNames: string[] = [];

	async function checkIfNotMissingIndices(loggedInPage: any, mode = 'singleSite') {
		const healthUrl =
			mode === 'network'
				? 'network/admin.php?page=elasticpress-health'
				: 'admin.php?page=elasticpress-health';

		await goToAdminPage(loggedInPage, healthUrl);

		// Use Playwright's expect with page locators for better error messages
		await expect(loggedInPage.locator('.wrap')).not.toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);

		// Check each index individually for better test feedback
		const indicesToCheck = mode === 'singleSite' ? indexNames : indexAllSitesNames;
		const metaboxText = await loggedInPage.locator('.metabox-holder').textContent();
		for (const index of indicesToCheck) {
			expect(metaboxText).toContain(index);
		}
	}

	test.describe('wp elasticpress sync', () => {
		test('Can index all the posts of the current blog', async ({ loggedInPage }) => {
			const result = await wpCli('wp elasticpress sync');
			expect(result.toString()).toContain('Indexing posts');
			expect(result.toString()).toContain('Number of posts indexed');

			await checkIfNotMissingIndices(loggedInPage);
		});

		test('Can clear the index in Elasticsearch, put the mapping again and then index all the posts if user specifies --setup argument', async ({
			loggedInPage,
		}) => {
			const syncResult = await wpCli('wp elasticpress sync --setup --yes');
			expect(syncResult.toString()).toContain('Mapping sent');
			expect(syncResult.toString()).toContain('Indexing posts');
			expect(syncResult.toString()).toContain('Number of posts indexed');

			const statsResult = await wpCli('wp elasticpress stats');
			expect(statsResult.toString()).toContain('Documents');
			expect(statsResult.toString()).toContain('Index Size');

			await checkIfNotMissingIndices(loggedInPage);
		});

		test('Can process that many posts in bulk index per round if user specifies --per-page parameter', async () => {
			const result = await wpCli('wp elasticpress sync --per-page=20');
			const output = result.toString();
			expect(output).toContain('Indexing posts');
			expect(output).toContain('20 of');
			expect(output).toContain('40 of');
			expect(output).toContain('Number of posts indexed');
		});

		test('Can index one post at a time if user specifies --nobulk parameter', async () => {
			const result = await wpCli('wp elasticpress sync --nobulk');
			const output = result.toString();
			expect(output).toContain('Indexing posts');
			expect(output).toContain('1 of');
			expect(output).toContain('2 of');
			expect(output).toContain('3 of');
			expect(output).toContain('4 of');
			expect(output).toContain('Number of posts indexed');
		});

		test('Can skip X posts and index the remaining if user specifies --offset parameter', async () => {
			const result = await wpCli('wp elasticpress sync --offset=10');
			const output = result.toString();
			expect(output).toContain('Indexing posts');
			expect(output).toContain('Skipping 10');

			const match1 = output.match(/(\d+) of (?<total>\d+)\./);
			const match2 = output.match(/Number of posts indexed: (?<indexed>\d+)/);

			if (match1?.groups && match2?.groups) {
				const total = parseInt(match1.groups.total);
				const indexed = parseInt(match2.groups.indexed);
				expect(10).toBe(total - indexed);
			}

			expect(output).toContain('Number of posts indexed');
		});

		test('Can index all the posts of a type if user specify --post-type parameter', async () => {
			const postTypeResult = await wpCli('wp elasticpress sync --post-type=post');
			const postTypeOutput = postTypeResult.toString();
			expect(postTypeOutput).toContain('Indexing posts');

			const postTypeMatch = postTypeOutput.match(/Number of posts indexed: (?<indexed>\d+)/);
			const indexPerPostType = postTypeMatch?.groups?.indexed;

			const totalResult = await wpCli('wp elasticpress sync');
			const totalOutput = totalResult.toString();
			expect(totalOutput).toContain('Indexing posts');

			const totalMatch = totalOutput.match(/Number of posts indexed: (?<indexed>\d+)/);
			const indexTotal = totalMatch?.groups?.indexed;

			expect(indexPerPostType).not.toBe(indexTotal);
		});

		test('Can index without using dynamic bulk requests if user specifies --static-bulk parameter', async ({
			loggedInPage,
		}) => {
			await activatePlugin(loggedInPage, 'fake-log-messages', 'wpCli');

			const result = await wpCli('wp elasticpress sync --static-bulk');
			const output = result.toString();
			expect(output).toContain('Index command with --static-bulk flag completed');
			expect(output).toContain('Done');

			await deactivatePlugin(loggedInPage, 'fake-log-messages', 'wpCli');
		});

		test('Can stop ongoing syncs with the --force flag', async () => {
			// Mock the sync process
			await wpCliEval(
				`update_option('ep_index_meta', [ 'indexing' => true ] ); set_transient('ep_sync_interrupted', true);`,
			);

			const result = await wpCli('wp elasticpress sync --force --yes');
			expect(result.toString()).toContain('Sync cleared');
		});
	});

	test('Can delete the index of current blog if user runs wp elasticpress delete-index', async ({
		loggedInPage,
	}) => {
		const deleteResult = await wpCli('wp elasticpress delete-index --yes');
		expect(deleteResult.toString()).toContain('Index deleted');

		const statsResult = await wpCli('wp elasticpress stats', true);
		expect(statsResult.toString()).toContain('is not currently indexed');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress-health');
		await expect(loggedInPage.locator('.wrap')).toContainText(
			'We could not find any data for your Elasticsearch indices.',
		);
	});

	test('Can put mapping of the current blog if user runs wp elasticpress put-mapping', async () => {
		const result = await wpCli('wp elasticpress put-mapping');
		const output = result.toString();
		expect(output).toContain('Adding post mapping');
		expect(output).toContain('Mapping sent');

		const networkResult = await wpCli('wp elasticpress put-mapping --network-wide');
		const networkOutput = networkResult.toString();
		expect(networkOutput).toContain('Adding post mapping');
		expect(networkOutput).toContain('Mapping sent');
	});

	test('Can recreate the alias index which points to every index in the network if user runs wp elasticpress recreate-network-alias command', async () => {
		// This test is intentionally empty in the original Cypress test
	});

	test('Can throw an error while running wp elasticpress recreate-network-alias if the plugin is not network activated', async () => {
		const result = await wpCli('wp elasticpress recreate-network-alias', true);
		expect(result.toString()).toContain('ElasticPress is not network activated');
	});

	test('Can activate and deactivate a feature', async () => {
		const alreadyActiveResult = await wpCli('wp elasticpress activate-feature search', true);
		expect(alreadyActiveResult.toString()).toContain('This feature is already active');

		const deactivateResult = await wpCli('wp elasticpress deactivate-feature search');
		expect(deactivateResult.toString()).toContain('Feature deactivated');

		const notActiveResult = await wpCli('wp elasticpress deactivate-feature search', true);
		expect(notActiveResult.toString()).toContain('Feature is not active');

		const activateResult = await wpCli('wp elasticpress activate-feature search');
		expect(activateResult.toString()).toContain('Feature activated');

		const invalidResult = await wpCli('wp elasticpress activate-feature invalid', true);
		expect(invalidResult.toString()).toContain('No feature with that slug is registered');

		const woocommerceResult = await wpCli('wp elasticpress activate-feature woocommerce', true);
		expect(woocommerceResult.toString()).toContain('Feature requirements are not met');

		const protectedContentResult = await wpCli(
			'wp elasticpress activate-feature protected_content',
			true,
		);
		expect(protectedContentResult.toString()).toContain('This feature requires a re-index');
		expect(protectedContentResult.toString()).toContain(
			'Feature is usable but there are warnings',
		);
	});

	test('Can list all the active features if user runs wp elasticpress list-features command', async () => {
		const result = await wpCli('wp elasticpress list-features');
		expect(result.toString()).toContain('Active features');
	});

	test('Can list all the registered features if user runs wp elasticpress list-features --all command', async () => {
		const result = await wpCli('wp elasticpress list-features --all');
		expect(result.toString()).toContain('Registered features');
	});

	test('Can return a string indicating the index is not running', async () => {
		const result = await wpCli('wp elasticpress get-ongoing-sync-status');
		expect(result.toString()).toContain(
			'{"indexing":false,"method":"none","items_indexed":0,"total_items":-1}',
		);
	});

	test('Can return a string indicating with the appropriate fields if user runs wp elasticpress get-last-cli-sync command', async () => {
		await wpCli('wp elasticpress sync');

		const result = await wpCli('wp elasticpress get-last-cli-sync --clear');
		expect(result.toString()).toContain('"total_time"');

		const clearResult = await wpCli('wp elasticpress get-last-cli-sync --clear');
		expect(clearResult.toString()).toContain('[]');
	});

	test.describe('multisite parameters', () => {
		test.beforeAll(async ({ browser }) => {
			const context = await browser.newContext();
			const page = await context.newPage();
			await activatePlugin(page, 'elasticpress', 'wpCli', 'network');
			const indicesResult = await wpCli('elasticpress get-indices');
			indexAllSitesNames = JSON.parse(indicesResult.toString());
			await context.close();
		});

		test.afterAll(async ({ browser }) => {
			const context = await browser.newContext();
			const page = await context.newPage();
			await deactivatePlugin(page, 'elasticpress', 'wpCli', 'network');
			await activatePlugin(page, 'elasticpress', 'wpCli');
			await context.close();
		});

		test('Can index all blogs in network if user specifies --network-wide argument', async ({
			loggedInPage,
		}) => {
			const result = await wpCli('wp elasticpress sync --network-wide');
			const output = result.toString();

			const siteMatches = output.match(/Indexing posts on site/g) || [];
			expect(siteMatches.length).toBe(2);

			const indexedMatches = output.match(/Number of posts indexed on site/g) || [];
			expect(indexedMatches.length).toBe(2);

			expect(output).toContain('Network alias created');

			await checkIfNotMissingIndices(loggedInPage, 'network');
		});

		test('Can index only current site if user does not specify --network-wide argument', async ({
			loggedInPage,
		}) => {
			const result = await wpCli('wp elasticpress sync');
			const output = result.toString();

			const siteMatches = output.match(/Indexing posts on site/g) || [];
			expect(siteMatches.length).toBe(1);

			const indexedMatches = output.match(/Number of posts indexed on site/g) || [];
			expect(indexedMatches.length).toBe(1);

			expect(output).not.toContain('Network alias created');

			await checkIfNotMissingIndices(loggedInPage, 'network');
		});

		test('Can index only site in the --url parameter if user does not specify --network-wide argument', async ({
			loggedInPage,
		}) => {
			const baseUrl = process.env.BASE_URL || 'http://localhost:8889';
			const result = await wpCli(`wp elasticpress sync --url=${baseUrl}/second-site`);
			const output = result.toString();

			const siteMatches = output.match(/Indexing posts on site/g) || [];
			expect(siteMatches.length).toBe(1);

			const indexedMatches = output.match(/Number of posts indexed on site/g) || [];
			expect(indexedMatches.length).toBe(1);

			expect(output).not.toContain('Network alias created');

			await checkIfNotMissingIndices(loggedInPage, 'network');
		});

		test('Can delete all the indices and put mappings for the entire network-wide', async ({
			loggedInPage,
		}) => {
			const deleteResult = await wpCli('wp elasticpress delete-index --network-wide --yes');
			const deleteOutput = deleteResult.toString();
			expect(deleteOutput).toContain('Deleting post index for site');
			expect(deleteOutput).toContain('Index deleted');

			await goToAdminPage(loggedInPage, 'network/admin.php?page=elasticpress-health');
			await expect(loggedInPage.locator('.wrap')).toContainText(
				'We could not find any data for your Elasticsearch indices.',
			);

			const mappingResult = await wpCli('wp elasticpress put-mapping --network-wide');
			const mappingOutput = mappingResult.toString();
			expect(mappingOutput).toContain('Adding post mapping for site');
			expect(mappingOutput).toContain('Mapping sent');

			await checkIfNotMissingIndices(loggedInPage, 'network');
		});
	});

	test('Can set the algorithm version', async () => {
		const defaultResult = await wpCli('wp elasticpress set-algorithm-version --default');
		expect(defaultResult.toString()).toContain('Done');

		const getDefaultResult = await wpCli('wp elasticpress get-algorithm-version');
		expect(getDefaultResult.toString()).toContain('default');

		const setVersionResult = await wpCli(
			'wp elasticpress set-algorithm-version --version=1.0.0',
		);
		expect(setVersionResult.toString()).toContain('Done');

		const getVersionResult = await wpCli('wp elasticpress get-algorithm-version');
		expect(getVersionResult.toString()).toContain('1.0.0');

		const errorResult = await wpCli('wp elasticpress set-algorithm-version', true);
		expect(errorResult.toString()).toContain(
			'This command expects a version number or the --default flag',
		);
	});

	test('Can get the mapping information', async () => {
		const result = await wpCli('wp elasticpress get-mapping');
		expect(result.toString()).toContain('mapping_version');
	});

	test('Can get the cluster indices information', async () => {
		const result = await wpCli('wp elasticpress get-cluster-indices');
		expect(result.toString()).toContain('health');
	});

	test('Can get the indices names', async () => {
		const result = await wpCli('wp elasticpress get-indices');
		expect(result).toBeDefined();

		const prettyResult = await wpCli('wp elasticpress get-indices --pretty');
		expect(prettyResult.toString()).toContain('\n');
	});

	test('Can stop the sync operation and clear it', async () => {
		// If no sync process is running, this will fail
		const stopResult = await wpCli('elasticpress stop-sync', true);
		expect(stopResult.toString()).toContain('There is no indexing operation running');

		// Mock the sync process
		await wpCliEval(
			`update_option('ep_index_meta', [ 'indexing' => true ] ); set_transient('ep_sync_interrupted', true);`,
		);

		const stopAfterMockResult = await wpCli('wp elasticpress stop-sync');
		expect(stopAfterMockResult.toString()).toContain('Done');

		const clearResult = await wpCli('wp elasticpress clear-sync');
		expect(clearResult.toString()).toContain('Sync cleared');
	});

	test('can send an HTTP request to Elasticsearch', async () => {
		const result = await wpCli('wp elasticpress request _cat/indices');
		expect(result).toBeDefined();

		// Check if it throws an error if non-supported method is used
		const postResult = await wpCli('wp elasticpress request _cat/indices --method=POST');
		expect(postResult.toString()).toContain('Incorrect HTTP method for uri');

		// Check if it prints the debugging info
		const debugResult = await wpCli(
			'wp elasticpress request _cat/indices --debug-http-request',
		);
		expect(debugResult.toString()).toContain(
			'[http_response] => WP_HTTP_Requests_Response Object',
		);
	});
});
