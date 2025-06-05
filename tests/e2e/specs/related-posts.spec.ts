import { test, expect } from '../fixtures';
import {
	maybeEnableFeature,
	publishPost,
	emptyWidgets,
	createClassicWidget,
	activatePlugin,
	deactivatePlugin,
	goToAdminPage,
	wpCliEval,
	maybeOpenSettingsTab,
	getEditorFrame,
} from '../utils';
import {
	openBlockInserter,
	closeBlockInserter,
	insertBlock,
	supportsBlockColors,
	supportsBlockTypography,
	supportsBlockDimensions,
} from '../block-editor';

test.describe('Related Posts Feature', () => {
	test.beforeAll(async () => {
		await maybeEnableFeature('related_posts');
	});

	test.beforeEach(async () => {
		await emptyWidgets();
		await wpCliEval(`
			WP_CLI::runcommand( 'plugin deactivate classic-widgets', [ 'return' => true ] );

			$posts_ids = WP_CLI::runcommand( 'post list --s="Test related posts" --ep_integrate=false --format=ids', [ 'return' => true ] );
			if ( $posts_ids ) {
				WP_CLI::runcommand( "post delete {$posts_ids} --force" );
			}
		`);
	});

	test('Can insert, configure, and use the Related Posts block', async ({ loggedInPage }) => {
		// Create some posts that will be related
		await wpCliEval(`
			for ( $i = 1; $i <= 4; $i++ ) {
				wp_insert_post(
					[
						'post_title'   => "Test related posts block #{$i}",
						'post_content' => 'Inceptos tristique class ac eleifend leo',
						'post_status'  => 'publish',
					]
				);
			}
		`);

		await publishPost(loggedInPage, {
			title: 'Test related posts block #5',
			content: 'Inceptos tristique class ac eleifend leo.',
		});

		// Get the editor frame
		const editorFrame = await getEditorFrame(loggedInPage);

		// Insert Related Posts block
		await openBlockInserter(loggedInPage);
		await insertBlock(loggedInPage, 'Related Posts');
		await closeBlockInserter(loggedInPage);

		// Verify block is inserted and contains expected content
		const block = editorFrame.locator('.wp-block.wp-block-elasticpress-related-posts').first();
		const listItems = block.locator('li');
		await expect(listItems).toHaveCount(5);
		await expect(listItems.first()).toContainText('Test related posts block #');

		// Configure block to show 2 posts
		await block.click();
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await loggedInPage
			.locator('input[type="number"]')
			.and(loggedInPage.getByLabel('Number of items'))
			.fill('2');

		// Verify block shows 2 posts
		await expect(listItems).toHaveCount(2);

		// Test block style support
		await supportsBlockColors(loggedInPage, block, true);
		await supportsBlockTypography(loggedInPage, block, true);
		await supportsBlockDimensions(loggedInPage, block, true);

		// Clicking a related post link shouldn't change URL
		const firstLink = block.locator('a').first();
		await firstLink.dispatchEvent('click');
		await expect(loggedInPage).toHaveURL(/wp-admin\/post\.php/);

		// Update post and visit front end
		const buttonLabel = process.env.WP_VERSION === '6.2' ? 'Update' : 'Save';
		await loggedInPage.getByRole('button', { name: buttonLabel, exact: true }).click();

		let viewPostLink;
		if (process.env.WP_VERSION === '6.2') {
			await loggedInPage.reload();
			viewPostLink = loggedInPage.locator('#wp-admin-bar-view a');
		} else {
			viewPostLink = loggedInPage.locator('a[aria-label="View Post"]');
		}

		const postHref = (await viewPostLink.getAttribute('href')) ?? '';
		await loggedInPage.goto(postHref);

		// Verify block on front end
		const frontEndBlock = loggedInPage.locator('.wp-block-elasticpress-related-posts').first();
		const frontEndItems = frontEndBlock.locator('li');
		await expect(frontEndItems).toHaveCount(2);
		await expect(frontEndItems.first()).toContainText('Test related posts block #');

		// Verify block style support on front end
		await supportsBlockColors(loggedInPage, frontEndBlock);
		await supportsBlockTypography(loggedInPage, frontEndBlock);
		await supportsBlockDimensions(loggedInPage, frontEndBlock);
	});

	test('Can insert, configure, use, and transform the legacy Related Posts widget', async ({
		loggedInPage,
	}) => {
		// Add legacy widget
		await activatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await createClassicWidget(loggedInPage, 'ep-related-posts', [
			{
				name: 'title',
				type: 'text',
				value: 'My related posts widget',
			},
			{
				name: 'num_posts',
				type: 'text',
				value: '2',
			},
		]);

		// Create related posts
		await wpCliEval(`
			for ( $i = 1; $i <= 2; $i++ ) {
				wp_insert_post(
					[
						'post_title'   => "Test related posts widget #{$i}",
						'post_content' => 'Inceptos tristique class ac eleifend leo',
						'post_status'  => 'publish',
					]
				);
			}
		`);

		await publishPost(
			loggedInPage,
			{
				title: 'Test related posts widget #3',
				content: 'Inceptos tristique class ac eleifend leo.',
			},
			true,
		);

		// Verify widget on front end
		const widget = loggedInPage.locator('[id^="ep-related-posts"]').first();
		await expect(widget).toContainText('My related posts widget');
		const widgetItems = widget.locator('li');
		await expect(widgetItems).toHaveCount(2);
		await expect(widgetItems.first()).toContainText('Test related posts widget #');

		// Visit block-based Widgets screen
		await deactivatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await goToAdminPage(loggedInPage, 'widgets.php');

		// Verify widget is transformed to Legacy Widget block
		const legacyWidget = loggedInPage.locator('.wp-block-legacy-widget').first();
		await expect(legacyWidget).toContainText('ElasticPress - Related Posts');

		// Transform to Related Posts block
		await legacyWidget.click();
		await loggedInPage.click('.block-editor-block-switcher button');
		await loggedInPage.click(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-related-posts',
		);

		// Verify transformation
		await expect(loggedInPage.locator('.wp-block-heading')).toContainText(
			'My related posts widget',
		);
		const block = loggedInPage.locator('.wp-block-elasticpress-related-posts').first();
		await expect(block).toBeVisible();

		// Verify block settings match widget settings
		await block.click();
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await expect(
			loggedInPage
				.locator('input[type="number"]')
				.and(loggedInPage.getByLabel('Number of items')),
		).toHaveValue('2');
	});
});
