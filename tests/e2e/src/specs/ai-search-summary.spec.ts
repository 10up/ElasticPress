import { test, expect } from '../fixtures.js';
import {
	goToAdminPage,
	wpCli,
	wpCliEval,
	maybeEnableFeature,
	maybeDisableFeature,
	setVectorEmbeddingsSettings,
} from '../utils.js';
import { insertBlock, getBlocksList, openBlockInserter } from '../block-editor.js';

test.describe('AI Search Summary Feature', { tag: '@group2' }, () => {
	test.afterAll(async () => {
		await maybeDisableFeature('ai_search_summary');
		await maybeDisableFeature('semantic_search');
		await maybeDisableFeature('vector_embeddings');
		await wpCli('option delete ep_vector_embeddings_settings', true);
		// Restore the sidebar search widget modified by the widget insertion test.
		await wpCli('widget reset sidebar-1', true);
		await wpCli('widget add search sidebar-1', true);
		// Reset the Elasticsearch index mapping to a non-vector state so subsequent tests
		// are not affected by the vector field mapping created during this spec.
		await wpCli('elasticpress sync --setup --yes', true);
	});

	test('Can not turn the feature on if vector embeddings is not enabled', async ({
		loggedInPage,
	}) => {
		await maybeDisableFeature('vector_embeddings');
		await maybeDisableFeature('ai_search_summary');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'AI', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'AI Search Summary' }).click();

		await expect(
			loggedInPage.locator('.components-notice.is-error').filter({
				hasText: 'The Vector Embeddings feature must be enabled to use this feature.',
			}),
		).toBeVisible();
	});

	test('Can enable and configure the feature', async ({ loggedInPage }) => {
		await maybeEnableFeature('vector_embeddings');
		await maybeEnableFeature('semantic_search');
		await maybeEnableFeature('search_algorithm');
		await maybeDisableFeature('ai_search_summary');
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await setVectorEmbeddingsSettings(loggedInPage);

		// We need a kNN search algorithm to match the search down below.
		await loggedInPage.getByRole('button', { name: 'Other', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'Search Algorithm Version' }).click();
		await loggedInPage.getByLabel('kNN Cosine').check();

		await loggedInPage.getByRole('button', { name: 'AI', exact: true }).click();
		await loggedInPage.getByRole('button', { name: 'AI Search Summary' }).click();
		await expect(loggedInPage.locator('h3', { hasText: 'AI Search Summary' })).toBeVisible();
		await loggedInPage.getByRole('checkbox', { name: 'Enable' }).setChecked(true);

		await loggedInPage
			.getByLabel('OpenAI API Key')
			.fill(process.env.VECTOR_EMBEDDINGS_API_KEY || '');
		await loggedInPage
			.getByLabel('OpenAI Chat Completion API Url')
			.fill(process.env.AI_SEARCH_SUMMARY_API_URL || '');
		await loggedInPage
			.getByLabel('The name of the chat model to use')
			.fill(process.env.AI_SEARCH_SUMMARY_MODEL || '');

		// Wait for API request
		const apiResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/features*',
		);

		await loggedInPage.getByRole('button', { name: 'Save' }).click();

		await apiResponsePromise;

		const result = await wpCli('elasticpress list-features');
		expect(result.toString()).toContain('ai_search_summary');

		const wpCliEvalResult = await wpCliEval(`
			$posts = new \\WP_Query(
				[
					'post_type'      => [ 'page' ],
					'posts_per_page' => -1,
					'meta_key'       => 'ep_test',
					'meta_value'     => 'ai_search_summary',
				]
			);
			foreach ( $posts->posts as $post ) {
				wp_delete_post( $post->ID, true );
			}

			// wp_insert_post is not working here
			$page_id = WP_CLI::runcommand( 'post create --post_title="Test Page" --post_content="The name of my cat is Whiskers" --post_author=1 --post_status="publish"  --post_type="page" --meta_input="{\\"ep_test\\":\\"ai_search_summary\\"}" --porcelain', [ 'return' => true ] );
			$page_id = absint( $page_id );

			$return = WP_CLI::runcommand( 'elasticpress sync --setup --yes --show-errors --include=' . $page_id, [ 'return' => true ] );

			echo $return;
		`);

		const returnValue = wpCliEvalResult.toString();

		expect(returnValue).not.toContain('Number of posts index errors');

		await goToAdminPage(loggedInPage, 'widgets.php');
		await openBlockInserter(loggedInPage);
		await expect(await getBlocksList(loggedInPage)).toContainText('AI Search Summary');
		await insertBlock(loggedInPage, 'AI Search Summary');
		const responsePromise = loggedInPage.waitForResponse('**/wp-json/wp/v2/sidebars/*');
		await loggedInPage.getByRole('button', { name: 'Update' }).click();
		await responsePromise;

		// Wait for API request
		const aiSearchSummaryResponsePromise = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/ai-search-summary*',
		);

		await loggedInPage.goto('/?s=what+is+the+name+of+my+cat');
		await aiSearchSummaryResponsePromise;
		await expect(loggedInPage.locator('.ep-ai-search-summary-generated')).toContainText(
			'Whiskers',
		);
	});
});
