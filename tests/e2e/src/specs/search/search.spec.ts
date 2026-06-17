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

		// ===== TEMP DIAGNOSTIC 2 (remove after CI run) =====
		// Confirmed: cli & web share ONE index and the docs ARE indexed (count
		// grows). Now check whether the indexed "Duplicated post" doc actually has
		// searchable title/content, via a direct ES query — isolating "doc indexed
		// empty" vs "WP frontend search layer not matching a good doc".
		const diag = await wpCliEval(`
			$index = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$es = \\ElasticPress\\Elasticsearch::factory();
			$opts = [ 'method' => 'POST', 'headers' => [ 'Content-Type' => 'application/json' ] ];
			$match = $es->remote_request( $index . '/_search', $opts + [ 'body' => wp_json_encode( [
				'size' => 3,
				'_source' => [ 'post_title', 'post_content', 'post_status', 'post_type' ],
				'query' => [ 'match' => [ 'post_title' => 'Duplicated post' ] ],
			] ) ] );
			$match_body = json_decode( wp_remote_retrieve_body( $match ), true );
			$any = $es->remote_request( $index . '/_search', $opts + [ 'body' => wp_json_encode( [
				'size' => 2,
				'_source' => [ 'post_title', 'post_status', 'post_type' ],
				'query' => [ 'match_all' => (object) [] ],
			] ) ] );
			$any_body = json_decode( wp_remote_retrieve_body( $any ), true );
			echo wp_json_encode( [
				'index' => $index,
				'matchDuplicatedTotal' => $match_body['hits']['total'] ?? null,
				'matchSources' => array_map( function ( $h ) { return $h['_source'] ?? null; }, $match_body['hits']['hits'] ?? [] ),
				'anyDocSources' => array_map( function ( $h ) { return $h['_source'] ?? null; }, $any_body['hits']['hits'] ?? [] ),
			] );
		`);
		// eslint-disable-next-line no-console
		console.log('EP-DIAG2', diag?.toString());
		// ===== END TEMP DIAGNOSTIC 2 =====

		await loggedInPage.goto(`/?s=duplicated+post`);
		// EP-DIAG2 also attached to this assertion message so it lands in the artifact.
		await expect(
			loggedInPage.locator('.site-content article:nth-of-type(1) h2'),
			`EP-DIAG2 ${diag}`,
		).toHaveText(postTitle);
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
