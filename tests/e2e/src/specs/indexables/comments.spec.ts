import {
	insertBlock,
	supportsBlockColors,
	supportsBlockDimensions,
	supportsBlockTypography,
} from '../../block-editor.js';
import { test, expect } from '../../fixtures.js';
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
	getSyncTimeout,
	openSyncLog,
} from '../../utils.js';

test.describe('Comments Indexable', { tag: '@group2' }, () => {
	test.describe.configure({ timeout: 120000 });

	const getCommentsCount = async () => {
		const statsResult = await wpCli('wp elasticpress stats', true);
		const text = statsResult?.toString() ?? '';
		const commentDocs = text.match(/-comment-[\s\S]*?Documents:\s+(\d+)/);
		if (commentDocs) {
			return parseInt(commentDocs[1], 10);
		}
		return 0;
	};

	const waitForCommentsCount = async (expected: number) => {
		await expect
			.poll(
				async () => {
					await refreshIndex('comment');
					return getCommentsCount();
				},
				{ timeout: getSyncTimeout() },
			)
			.toBe(expected);
	};

	/**
	 * A sync run while the comments feature was inactive leaves the comment index
	 * missing. Hosted Elasticsearch does not create indices on write, so comments
	 * added afterwards would never be indexed. Rebuilding it also makes the
	 * document count a reliable baseline.
	 */
	const rebuildCommentIndex = async () => {
		await wpCli('elasticpress sync --setup --yes --indexables=comment');
		// The counts below come from index stats, which only report the comments
		// just indexed once the index has been refreshed.
		await refreshIndex('comment');
	};

	test.beforeAll(async () => {
		await wpCliEval(`
			update_option( 'require_name_email', '1' );
			update_option( 'comment_moderation', '1' );
			update_option( 'comment_previously_approved', '1' );
			WP_CLI::runcommand( 'plugin activate show-comments-and-terms', [ 'return' => true ] );
			\\ElasticPress\\Features::factory()->update_feature( 'comments', [ 'active' => true ], true );

			// Posts below are titled with a timestamp, so a new one is created on
			// every run. Remove earlier batches, along with their comments, to
			// keep the document counts these tests assert on stable. Matching has
			// to be anchored to the title: a search would also match the fixture
			// posts these tests rely on.
			global $wpdb;
			foreach ( [ 'Test Comment ', 'Anonymous Comment Sync ' ] as $stale_prefix ) {
				$stale_posts = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s",
						$wpdb->esc_like( $stale_prefix ) . '%'
					)
				);
				foreach ( $stale_posts as $stale_post ) {
					wp_delete_post( $stale_post, true );
				}
			}
		`);
	});

	test.beforeEach(async ({ loggedInPage }) => {
		await emptyWidgets();
		await deactivatePlugin(loggedInPage, 'classic-widgets', 'wpCli');
	});

	test.afterAll(async () => {
		await maybeDisableFeature('comments');
	});

	test('Can insert, configure, and use the Search Comments block', async ({ loggedInPage }) => {
		const setTitle = async (title: string) => {
			await loggedInPage
				.locator('.wp-block-elasticpress-comments')
				.last()
				.locator('.rich-text')
				.fill(title);
		};

		const inspector = loggedInPage.locator('.block-editor-block-inspector');

		/**
		 * Restrict the block that was just inserted to the given post types.
		 *
		 * @param {string[]} postTypeLabels Plural labels of the post types to search.
		 */
		const searchPostTypes = async (postTypeLabels: string[]) => {
			await maybeOpenSettingsTab(loggedInPage, 'Block');

			// A newly inserted block searches every post type. Waiting for that
			// state confirms the inspector has caught up with the insertion,
			// rather than still describing the previous block.
			await expect(
				inspector.getByRole('checkbox', { name: 'Search all comments', exact: true }),
			).toBeChecked();

			for await (const postTypeLabel of postTypeLabels) {
				const postTypeCheckbox = inspector.getByRole('checkbox', {
					name: `Search comments on ${postTypeLabel}`,
					exact: true,
				});

				await postTypeCheckbox.click();
				await expect(postTypeCheckbox).toBeChecked();
			}
		};

		await goToAdminPage(loggedInPage, 'widgets.php');

		// Get the editor frame
		const editorFrame = await getEditorFrame(loggedInPage);

		// Insert Search Comments block
		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search all comments');

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on posts');
		await searchPostTypes(['Posts']);

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on pages');
		await searchPostTypes(['Pages']);

		await insertBlock(loggedInPage, 'Search Comments');
		await setTitle('Search comments on pages and posts');
		await searchPostTypes(['Posts', 'Pages']);

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
		await createClassicWidget('ep-comments', [
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

		await openSyncLog(loggedInPage);

		const syncMessages = loggedInPage.locator('.ep-sync-messages');
		await expect(syncMessages).toContainText('Mapping sent', { timeout: 60000 });
		await expect(syncMessages).toContainText('Sync complete', { timeout: 60000 });
		await expect(syncMessages).toContainText('Number of comments indexed', { timeout: 60000 });

		const listFeaturesResult = await wpCli('elasticpress list-features');
		expect(listFeaturesResult.toString()).toContain('comments');
	});

	test('Can not sync anonymous comments until it is approved manually', async ({
		browser,
		loggedInPage,
	}) => {
		await maybeEnableFeature('comments');
		await wpCli('option update require_name_email 0');

		const postTitle = `Test Comment ${Date.now()}`;
		await publishPost(loggedInPage, {
			title: postTitle,
		});

		// Publish comment as a logged out user
		// await logout(loggedInPage);

		const anonymousContext = await browser.newContext();
		const anonymousPage = await anonymousContext.newPage();

		await expect(anonymousPage.locator('#wpadminbar')).not.toBeVisible();

		await anonymousPage.goto('/');
		await anonymousPage.locator('#main .entry-title a').getByText(postTitle).first().click();
		await anonymousPage.locator('#comment').fill('This is a anonymous comment');
		await anonymousPage.locator('#submit').click();
		await anonymousPage.waitForLoadState('networkidle');
		await anonymousContext.close();

		const syncResult1 = await wpCli('wp elasticpress sync');
		expect(syncResult1.toString()).toContain('Number of comments indexed');

		const commentsStartCount = await getCommentsCount();

		// Approve the comment
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=moderated');
		const ajaxRequest1 = loggedInPage.waitForResponse('**/wp-admin/admin-ajax.php*');
		await loggedInPage.locator('.approve a').first().dispatchEvent('click');
		const response1 = await ajaxRequest1;
		expect(response1.status()).toBe(200);

		await refreshIndex('comment');
		await waitForCommentsCount(commentsStartCount + 1);

		// Trash the comment
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=approved');
		await loggedInPage.locator('.column-comment .trash a').first().dispatchEvent('click');

		await refreshIndex('comment');
		await waitForCommentsCount(commentsStartCount);
	});

	test('Can sync woocommerce reviews', async ({ loggedInPage }) => {
		await activatePlugin(loggedInPage, 'woocommerce', 'wpCli');
		await maybeEnableFeature('comments');
		await maybeEnableFeature('woocommerce');

		await rebuildCommentIndex();
		const commentsStartCount = await getCommentsCount();

		// Recent WooCommerce versions install with coming soon mode enabled, which
		// puts a notice bar over the bottom of store pages. The review is
		// submitted with a forced click, so that bar silently receives it
		// instead of the button.
		await wpCli('option update woocommerce_coming_soon no');

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
		await waitForCommentsCount(commentsStartCount + 1);

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
		await maybeEnableFeature('comments');

		await rebuildCommentIndex();
		const commentsStartCount = await getCommentsCount();

		const postTitle = `Anonymous Comment Sync ${Date.now()}`;
		await publishPost(loggedInPage, {
			title: postTitle,
		});

		const viewPostLink = loggedInPage.locator(
			'a[aria-label="View Post"], .post-publish-panel__postpublish-buttons a:has-text("View Post"), #wp-admin-bar-view a',
		);
		const postUrl = (await viewPostLink.first().getAttribute('href')) || '/';

		// Disable settings
		await wpCliEval(`
			update_option( 'require_name_email', '0' );
			update_option( 'comment_moderation', '0' );
			update_option( 'comment_previously_approved', '0' );
		`);

		await logout(loggedInPage);

		// Publish comment as a logged out user
		await loggedInPage.goto(postUrl);
		await loggedInPage.locator('#comment').fill(`This is a anonymous comment ${Date.now()}`);
		await loggedInPage.locator('#submit').click();
		await loggedInPage.waitForLoadState('networkidle');

		await waitForCommentsCount(commentsStartCount + 1);

		// Row-action links sit outside the viewport until hover.
		await login(loggedInPage);
		await goToAdminPage(loggedInPage, 'edit-comments.php?comment_status=approved');
		await loggedInPage.locator('.column-comment .trash a').first().dispatchEvent('click');
	});
});
