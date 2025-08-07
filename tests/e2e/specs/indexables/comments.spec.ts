import {
	insertBlock,
	supportsBlockColors,
	supportsBlockDimensions,
	supportsBlockTypography,
} from '../../block-editor';
import { test, expect } from '../../fixtures';
import {
	goToAdminPage,
	activatePlugin,
	deactivatePlugin,
	publishPost,
	maybeEnableFeature,
	maybeDisableFeature,
	logout,
	login,
	emptyWidgets,
	createClassicWidget,
	wpCli,
	refreshIndex,
	wpCliEval,
	getEditorFrame,
	maybeOpenSettingsTab,
} from '../../utils';

test.describe('Comments Indexable', { tag: '@group2' }, () => {
	const getCommentsCount = async () => {
		const statsResult = await wpCli('wp elasticpress stats');
		const counts = [...statsResult.toString().matchAll(/Documents:\s+(\d+)/g)];
		return parseInt(counts?.[1]?.[1] || 0, 10);
	};

	test.beforeAll(async () => {
		await wpCliEval(`
			update_option( 'require_name_email', '1' );
			update_option( 'comment_moderation', '1' );
			update_option( 'comment_previously_approved', '1' );
			WP_CLI::runcommand( 'plugin activate show-comments-and-terms', [ 'return' => true ] );
			\\ElasticPress\\Features::factory()->update_feature( 'comments', [ 'active' => true ], true );
		`);
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await emptyWidgets();
		await deactivatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
	});

	test('Can insert, configure, and use the Search Comments block', async ({ loggedInPage }) => {
		const setTitle = async (title: string) => {
			await loggedInPage
				.locator('.wp-block-elasticpress-comments')
				.last()
				.locator('.rich-text')
				.fill(title);
		};

		await goToAdminPage(loggedInPage, 'widgets.php');

		// Get the editor frame
		const editorFrame = await getEditorFrame(loggedInPage);

		// Insert Search Comments block
		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search all comments');

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on posts');
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await loggedInPage.locator('.components-checkbox-control__input').nth(1).click();

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on pages');
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await loggedInPage.locator('.components-checkbox-control__input').nth(2).click();

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on pages and posts');
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await loggedInPage.locator('.components-checkbox-control__input').nth(1).click();
		await loggedInPage.locator('.components-checkbox-control__input').nth(2).click();

		// Test block style support
		const block = editorFrame.locator('.wp-block-elasticpress-comments').last();
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await supportsBlockColors(loggedInPage, block, true);
		await supportsBlockTypography(loggedInPage, block, true);
		await supportsBlockDimensions(loggedInPage, block, true);

		// Update widgets and visit front end
		const saveWidgetsRequest = loggedInPage.waitForResponse('**/wp-json/wp/v2/sidebars*');
		await loggedInPage
			.locator('.edit-widgets-header')
			.getByRole('button', { name: 'Update', exact: true })
			.click();
		await saveWidgetsRequest;

		await loggedInPage.goto('/');

		/**
		 * Verify the all comments block has the expected markup and returns
		 * the expected results.
		 */
		const blocks = [
			{
				title: 'Search all comments',
				hidden: null,
				'these tests': true,
				Contributor: true,
			},
			{
				title: 'Search comments on posts',
				hidden: ['post'],
				'these tests': true,
				Contributor: false,
			},
			{
				title: 'Search comments on pages',
				hidden: ['page'],
				'these tests': false,
				Contributor: true,
			},
			{
				title: 'Search comments on pages and posts',
				hidden: ['post', 'page'],
				'these tests': true,
				Contributor: true,
			},
		];

		let i = 0;
		for await (const blockData of blocks) {
			const block = loggedInPage.locator('.wp-block-elasticpress-comments').nth(i++);
			await expect(block.locator('label')).toContainText(blockData.title);
			await expect(block.locator('input[type="hidden"]')).toHaveCount(
				blockData.hidden ? 1 : 0,
			);
			if (blockData.hidden) {
				await expect(block.locator('input[type="hidden"]')).toHaveAttribute(
					'value',
					blockData.hidden.join(','),
				);
			}

			const input = block.locator('input[type="search"]');
			await expect(input).toBeVisible();

			const commentsRequest = loggedInPage.waitForResponse(
				'**/wp-json/elasticpress/v1/comments*',
			);
			await input.pressSequentially('these tests');
			await commentsRequest;
			if (blockData['these tests']) {
				await expect(
					block.locator('li:has-text("These tests are amazing!")'),
				).toBeVisible();
			} else {
				await expect(
					block.locator('li:has-text("These tests are amazing!")'),
				).not.toBeVisible();
			}

			await input.clear();
			await input.pressSequentially('Contributor');
			await commentsRequest;
			if (blockData.Contributor) {
				await expect(block.locator('li:has-text("Contributor comment.")')).toBeVisible();
			} else {
				await expect(
					block.locator('li:has-text("Contributor comment.")'),
				).not.toBeVisible();
			}
		}

		const frontEndBlock = loggedInPage.locator('.wp-block-elasticpress-comments').last();
		await supportsBlockColors(loggedInPage, frontEndBlock);
		await supportsBlockTypography(loggedInPage, frontEndBlock);
		await supportsBlockDimensions(loggedInPage, frontEndBlock);
	});

	test('Can insert, configure, use, and transform the legacy Comments widget', async ({
		loggedInPage,
	}) => {
		/**
		 * Add the legacy widget.
		 */
		await activatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await createClassicWidget(loggedInPage, 'ep-comments', [
			{
				name: 'title',
				type: 'text',
				value: 'My comments widget',
			},
			{
				name: 'post_type][',
				type: 'checkbox',
				value: 'page',
			},
		]);

		/**
		 * Verify the comments widget has the expected markup and returns the
		 * expected results.
		 */
		await loggedInPage.goto('/');

		const widget = loggedInPage.locator('[id^="ep-comments"]').first();
		await expect(widget.locator('input[type="hidden"]')).toHaveAttribute('value', 'page');

		const input = widget.locator('input[type="search"]');
		await expect(input).toBeVisible();

		const commentsRequest1 = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/comments*',
		);
		await input.pressSequentially('these tests');
		await commentsRequest1;
		await expect(widget.locator('li:has-text("These tests are amazing!")')).not.toBeVisible();

		const commentsRequest2 = loggedInPage.waitForResponse(
			'**/wp-json/elasticpress/v1/comments*',
		);
		await input.clear();
		await input.pressSequentially('Contributor');
		await commentsRequest2;
		await expect(widget.locator('li:has-text("Contributor comment.")')).toBeVisible();

		/**
		 * Visit the block-based Widgets screen.
		 */
		await deactivatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
		await goToAdminPage(loggedInPage, 'widgets.php');

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		const legacyWidget = loggedInPage.locator('.wp-block-legacy-widget').first();
		await expect(legacyWidget).toContainText('ElasticPress - Comments');

		/**
		 * Transform the legacy widget into the block.
		 */
		await legacyWidget.click();
		await loggedInPage.locator('.block-editor-block-switcher button').click();
		await loggedInPage
			.locator(
				'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-comments',
			)
			.click();

		/**
		 * Check that the widget has been transformed into the correct blocks.
		 */
		await expect(loggedInPage.locator('.wp-block-heading')).toContainText('My comments widget');

		const block = loggedInPage.locator('.wp-block-elasticpress-comments').first();
		await expect(block).toBeVisible();

		// Verify block settings match widget settings
		await block.click();
		await maybeOpenSettingsTab(loggedInPage, 'Block');
		await expect(
			loggedInPage.locator('.components-checkbox-control__input').nth(0),
		).not.toBeChecked();
		await expect(
			loggedInPage.locator('.components-checkbox-control__input').nth(1),
		).not.toBeChecked();
		await expect(
			loggedInPage.locator('.components-checkbox-control__input').nth(2),
		).toBeChecked();
	});

	test('Can automatically start a sync if activate the feature', async ({ loggedInPage }) => {
		await maybeDisableFeature('comments');

		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');

		await loggedInPage.getByRole('button', { name: 'Other' }).click();
		await loggedInPage.getByRole('button', { name: 'Comments' }).click();
		await loggedInPage.getByRole('checkbox', { name: 'Enable' }).click();
		loggedInPage.on('dialog', (dialog) => dialog.accept());
		await loggedInPage.getByRole('button', { name: 'Save and sync now' }).click();

		await loggedInPage.locator('.components-button').getByText('Log').click();

		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent', { timeout: 60000 });
		await expect(syncMessages).toContainText('Sync complete', { timeout: 60000 });
		await expect(syncMessages).toContainText('Number of comments indexed', { timeout: 60000 });

		const listFeaturesResult = await wpCli('elasticpress list-features');
		expect(listFeaturesResult.toString()).toContain('comments');
	});

	test('Can not sync anonymous comments until it is approved manually', async ({
		page,
		loggedInPage,
	}) => {
		await maybeEnableFeature('comments');

		// Enable comments
		await wpCli('option update require_name_email 0');

		await publishPost(loggedInPage, {
			title: 'Test Comment',
		});

		// Publish comment as a logged out user
		await logout(page);
		await page.goto('/');
		await page.locator('#main .entry-title a').getByText('Test Comment').first().click();
		await page.locator('#comment').fill('This is a anonymous comment');
		await page.locator('#submit').click();

		const syncResult1 = await wpCli('wp elasticpress sync');
		expect(syncResult1.toString()).toContain('Number of comments indexed');

		const commentsStartCount = await getCommentsCount();

		// Approve the comment
		await login(loggedInPage);
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=moderated');
		const ajaxRequest1 = loggedInPage.waitForResponse('**/wp-admin/admin-ajax.php*');
		await loggedInPage.locator('.approve a').first().dispatchEvent('click');
		const response1 = await ajaxRequest1;
		expect(response1.status()).toBe(200);

		expect(await getCommentsCount()).toBe(commentsStartCount + 1);

		// Trash the comment
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=approved');
		await loggedInPage.locator('.column-comment .trash a').first().dispatchEvent('click');

		expect(await getCommentsCount()).toBe(commentsStartCount);
	});

	test('Can sync woocommerce reviews', async ({ loggedInPage }) => {
		const commentsStartCount = await getCommentsCount();

		await activatePlugin(loggedInPage, 'woocommerce', 'wpCli');
		await maybeEnableFeature('comments');
		await maybeEnableFeature('woocommerce');

		// Enable product reviews
		await loggedInPage.goto('/product/awesome-aluminum-shoes/');
		await loggedInPage.locator('#wp-admin-bar-edit a').click();
		await loggedInPage.waitForLoadState('domcontentloaded');
		await loggedInPage.locator('.advanced_options.advanced_tab a').click();
		await loggedInPage.locator('#comment_status').check();
		await loggedInPage.locator('#publish').click();

		// Visit product page and leave a review
		await loggedInPage.locator('#wp-admin-bar-view a').click();
		await loggedInPage.locator('#tab-title-reviews a').click();
		await loggedInPage.locator('.comment-form-rating .star-4').click();
		await loggedInPage.locator('#comment').fill(`This is a test review ${Date.now()}`);
		await loggedInPage.locator('#submit').click();

		// Check if the new comment was indexed
		await refreshIndex('comment');
		expect(await getCommentsCount()).toBe(commentsStartCount + 1);

		// Trash the review
		const wcVersionResult = await wpCli('plugin get woocommerce --field=version');
		const wcVersion = wcVersionResult.toString().trim();

		if (wcVersion === '6.4.0') {
			await goToAdminPage(
				loggedInPage,
				'edit-comments.php?comment_type=review&comment_status=approved',
			);
		} else {
			await goToAdminPage(
				loggedInPage,
				'edit.php?post_type=product&page=product-reviews&comment_status=approved',
			);
		}
		await loggedInPage.locator('.column-comment .trash a').first().dispatchEvent('click');

		await deactivatePlugin(loggedInPage, 'woocommerce', 'wpCli');
	});

	test('Can sync anonymous comments when settings are disabled', async ({ loggedInPage }) => {
		const commentsStartCount = await getCommentsCount();

		await maybeEnableFeature('comments');

		await goToAdminPage(loggedInPage, 'options-discussion.php');

		// Disable settings
		await wpCliEval(`
			update_option( 'require_name_email', '0' );
			update_option( 'comment_moderation', '0' );
			update_option( 'comment_previously_approved', '0' );
		`);

		await logout(loggedInPage);

		// Publish comment as a logged out user
		await loggedInPage.goto('/');
		await loggedInPage
			.locator('#main .entry-title a')
			.getByText('Test Comment')
			.first()
			.click();
		await loggedInPage.locator('#comment').fill('This is a anonymous comment');
		await loggedInPage.locator('#submit').click();

		expect(await getCommentsCount()).toBe(commentsStartCount + 1);

		// Trash the comment
		await login(loggedInPage);
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=approved');
		await loggedInPage.locator('.column-comment .trash a').first().click({ force: true });
	});
});
