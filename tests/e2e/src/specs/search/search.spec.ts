import { test, expect } from '../../fixtures.js';
import {
	wpCli,
	wpCliEval,
	publishPost,
	goToAdminPage,
	refreshIndex,
	setDefaultFeatureSettings,
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
		// Real-time sync of newly-created posts is unreliable on the CI runners
		// (WP 7.0), so explicitly re-index via the bulk path (the same path the
		// beforeAll setup uses) before searching, then force a refresh.
		await wpCli('elasticpress sync');
		await refreshIndex('post');
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

		// Real-time sync of newly-created posts is unreliable on the CI runners
		// (WP 7.0), so explicitly re-index via the bulk path (the same path the
		// beforeAll setup uses) before searching, then force a refresh.
		await wpCli('elasticpress sync');
		await refreshIndex('post');

		// ===== TEMP DIAGNOSTIC (remove after CI run) =====
		// Reveal the index landscape: which index the wp-cli context writes to,
		// vs the docker CID the web context resolves, plus every post index's doc
		// count. If the cli index != the web index, the unique-index-name mu-plugin
		// is splitting writes (wp-cli container) from reads (web container).
		const diag = await wpCliEval(`
			$post_index = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$cat = \\ElasticPress\\Elasticsearch::factory()->remote_request( '_cat/indices?format=json&h=index,docs.count' );
			$indices = json_decode( wp_remote_retrieve_body( $cat ), true );
			$post_indices = array_values( array_filter( (array) $indices, function ( $i ) {
				return isset( $i['index'] ) && strpos( $i['index'], '-post-' ) !== false;
			} ) );
			$cli_cid = function_exists( 'get_docker_cid' ) ? get_docker_cid() : 'n/a';
			echo wp_json_encode( [ 'cliIndex' => $post_index, 'cliCid' => $cli_cid, 'postIndices' => $post_indices ] );
		`);
		await goToAdminPage(loggedInPage, 'index.php');
		const webCid = await loggedInPage
			.locator('#docker-cid')
			.textContent()
			.catch(() => 'n/a');
		// eslint-disable-next-line no-console
		console.log('EP-DIAG cli=', diag?.toString(), ' webCid=', webCid);
		// ===== END TEMP DIAGNOSTIC =====

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
		await loggedInPage
			.getByRole('radio', { name: 'Weight results by date', exact: true })
			.click();
		await loggedInPage.getByRole('button', { name: 'Save changes' }).click();

		await responsePromise;

		await publishPost(loggedInPage, {
			title: 'test highlight color',
			content: 'findme findme findme',
		});

		// Real-time sync of newly-created posts is unreliable on the CI runners
		// (WP 7.0), so explicitly re-index via the bulk path (the same path the
		// beforeAll setup uses) before searching, then force a refresh.
		await wpCli('elasticpress sync');
		await refreshIndex('post');

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
