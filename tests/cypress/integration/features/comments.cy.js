// eslint-disable-next-line jest/valid-describe-callback
describe('Comments Feature', { tags: '@slow' }, () => {
	const defaultApprovedComments = 26;

	/**
	 * Ensure the feature is active and ensure Classic Widgets is installed
	 * before running tests.
	 */
	before(() => {
		cy.visitAdminPage('options-discussion.php');
		cy.get('#require_name_email').check();
		cy.get('#comment_moderation').check();
		cy.get('#comment_previously_approved').check();
		cy.get('#submit').click();
		cy.activatePlugin('show-comments-and-terms', 'wpCli');
		cy.maybeEnableFeature('comments');
	});

	/**
	 * Delete all widgets and ensure Classic Widgets is deactivated.
	 */
	beforeEach(() => {
		cy.emptyWidgets();
		cy.deactivatePlugin('classic-widgets', 'wpCli');
	});

	/**
	 * Test that the Related Posts block is functional.
	 */
	it('Can insert, configure, and use the Search Comments block', () => {
		/**
		 * Add a Block for searching all comments.
		 */
		cy.openWidgetsPage();
		cy.openBlockInserter();
		cy.insertBlock('Search Comments');
		cy.get('.wp-block-elasticpress-comments')
			.last()
			.find('.rich-text')
			.clearThenType('Search all comments');

		/**
		 * Add a Block for searching Post comments.
		 */
		cy.openBlockInserter();
		cy.insertBlock('Search Comments');
		cy.get('.wp-block-elasticpress-comments')
			.last()
			.find('.rich-text')
			.clearThenType('Search comments on posts');
		cy.openBlockSettingsSidebar();
		cy.get('.components-checkbox-control__input').eq(1).click();

		/**
		 * Add a Block for searching Page comments.
		 */
		cy.openBlockInserter();
		cy.insertBlock('Search Comments');
		cy.get('.wp-block-elasticpress-comments')
			.last()
			.find('.rich-text')
			.clearThenType('Search comments on pages');
		cy.openBlockSettingsSidebar();
		cy.get('.components-checkbox-control__input').eq(2).click();

		/**
		 * Add a Block for searching Page and Post comments.
		 */
		cy.openBlockInserter();
		cy.insertBlock('Search Comments');
		cy.get('.wp-block-elasticpress-comments').last().as('pagePostBlock');
		cy.get('@pagePostBlock')
			.find('.rich-text')
			.clearThenType('Search comments on pages and posts');
		cy.openBlockSettingsSidebar();
		cy.get('.components-checkbox-control__input').eq(1).click();
		cy.get('.components-checkbox-control__input').eq(2).click();

		/**
		 * Test that the block supports changing styles.
		 */
		cy.get('@pagePostBlock').supportsBlockColors(true);
		cy.get('@pagePostBlock').supportsBlockTypography(true);
		cy.get('@pagePostBlock').supportsBlockDimensions(true);

		/**
		 * Save widgets and visit the front page.
		 */
		cy.intercept('/wp-json/wp/v2/sidebars*').as('sidebarsRest');
		cy.get('.edit-widgets-header__actions button').contains('Update').click();
		cy.wait('@sidebarsRest');
		cy.visit('/');

		/**
		 * Wait for REST responses.
		 */
		cy.intercept('/wp-json/elasticpress/v1/comments*').as('commentsRest');

		/**
		 * Verify the all comments block has the expected markup and returns
		 * the expected results.
		 */
		cy.get('.wp-block-elasticpress-comments').eq(0).as('allBlock');
		cy.get('@allBlock').find('label').should('contain', 'Search all comments');
		cy.get('@allBlock').find('input[type="hidden"]').should('have.length', 0);
		cy.get('@allBlock').find('input[type="search"]').as('allInput').should('exist');

		cy.get('@allInput').clearThenType('these tests');
		cy.wait('@commentsRest');
		cy.get('@allBlock').find('li').should('contain', 'These tests are amazing!');
		cy.get('@allInput').clearThenType('Contributor');
		cy.wait('@commentsRest');
		cy.get('@allBlock').find('li').should('contain', 'Contributor comment.');

		/**
		 * Verify that the block supports changing styles.
		 */
		cy.get('.wp-block-elasticpress-comments').last().as('pagePostBlock');
		cy.get('@pagePostBlock').supportsBlockColors();
		cy.get('@pagePostBlock').supportsBlockTypography();
		cy.get('@pagePostBlock').supportsBlockDimensions();

		/**
		 * Verify the post comments block has the expected markup and returns
		 * the expected results.
		 */
		cy.get('.wp-block-elasticpress-comments').eq(1).as('postsBlock');
		cy.get('@postsBlock').find('label').should('contain', 'Search comments on posts');
		cy.get('@postsBlock').find('input[type="hidden"]').should('have.attr', 'value', 'post');
		cy.get('@postsBlock').find('input[type="search"]').as('postsInput').should('exist');

		cy.get('@postsInput').clearThenType('these tests');
		cy.wait('@commentsRest');
		cy.get('@postsBlock').find('li').should('contain', 'These tests are amazing!');
		cy.get('@postsInput').clearThenType('Contributor');
		cy.wait('@commentsRest');
		cy.get('@postsBlock').find('li').should('not.contain', 'Contributor comment.');

		/**
		 * Verify the page comments block has the expected markup and returns
		 * the expected results.
		 */
		cy.get('.wp-block-elasticpress-comments').eq(2).as('pagesBlock');
		cy.get('@pagesBlock').find('label').should('contain', 'Search comments on pages');
		cy.get('@pagesBlock').find('input[type="hidden"]').should('have.attr', 'value', 'page');
		cy.get('@pagesBlock').find('input[type="search"]').as('pagesInput').should('exist');

		cy.get('@pagesInput').clearThenType('these tests');
		cy.wait('@commentsRest');
		cy.get('@pagesBlock').find('li').should('not.contain', 'These tests are amazing!');
		cy.get('@pagesInput').clearThenType('Contributor');
		cy.wait('@commentsRest');
		cy.get('@pagesBlock').find('li').should('contain', 'Contributor comment.');

		/**
		 * Verify the post and page comments block has the expected markup
		 * and returns the expected results.
		 */
		cy.get('.wp-block-elasticpress-comments').eq(3).as('bothBlock');
		cy.get('@bothBlock').find('label').should('contain', 'Search comments on pages and posts');
		cy.get('@bothBlock').find('input[type="hidden"]').should('have.attr', 'value', 'post,page');
		cy.get('@bothBlock').find('input[type="search"]').as('bothInput').should('exist');

		cy.get('@bothInput').clearThenType('these tests');
		cy.wait('@commentsRest');
		cy.get('@bothBlock').find('li').should('contain', 'These tests are amazing!');
		cy.get('@bothInput').clearThenType('Contributor');
		cy.wait('@commentsRest');
		cy.get('@bothBlock').find('li').should('contain', 'Contributor comment.');
	});

	/**
	 * Test that the Comments widget is functional and can be transformed
	 * into the Search Comments block.
	 */
	it('Can insert, configure, use, and transform the legacy Comments widget', () => {
		/**
		 * Add the legacy widget.
		 */
		cy.activatePlugin('classic-widgets', 'wpCli');
		cy.createClassicWidget('ep-comments', [
			{
				name: 'title',
				value: 'My comments widget',
			},
			{
				name: 'post_type',
				type: 'checkbox',
				value: 'page',
			},
		]);

		/**
		 * Verify the comments widget has the expected markup and returns the
		 * expected results.
		 */
		cy.visit('/');
		cy.intercept('/wp-json/elasticpress/v1/comments*').as('commentsRest');
		cy.get(`[id^="ep-comments"]`).first().as('widget');
		cy.get('@widget').find('input[type="hidden"]').should('have.attr', 'value', 'page');
		cy.get('@widget').find('input[type="search"]').as('input').should('exist');

		cy.get('@input').clearThenType('these tests');
		cy.wait('@commentsRest');
		cy.get('@widget').find('li').should('not.contain', 'These tests are amazing!');
		cy.get('@input').clearThenType('Contributor');
		cy.wait('@commentsRest');
		cy.get('@widget').find('li').should('contain', 'Contributor comment.');

		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.deactivatePlugin('classic-widgets', 'wpCli');
		cy.openWidgetsPage();

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		cy.get(`.wp-block-legacy-widget`).first().as('widget');
		cy.get('@widget').should('contain.text', 'ElasticPress - Comments');

		/**
		 * Transform the legacywidget into the block.
		 */
		cy.get('@widget').click();
		cy.get('.block-editor-block-switcher button').click();
		cy.get(
			'.block-editor-block-switcher__popover .editor-block-list-item-elasticpress-comments',
		).click();

		/**
		 * Check that the widget has been transformed into the correct blocks.
		 */
		cy.get(`.wp-block-heading`).contains('My comments widget').should('exist');
		cy.get('.wp-block-elasticpress-comments').should('exist').first().as('block');

		/**
		 * Check that the block's settings match the widget's.
		 */
		cy.get('@block').click();
		cy.get('.components-checkbox-control__input').eq(0).should('not.be.checked');
		cy.get('.components-checkbox-control__input').eq(1).should('not.be.checked');
		cy.get('.components-checkbox-control__input').eq(2).should('be.checked');
	});

	it('Can automatically start a sync if activate the feature', () => {
		cy.login();

		cy.maybeDisableFeature('comments');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.intercept('/wp-json/elasticpress/v1/features*').as('apiRequest');

		cy.contains('button', 'Comments').click();
		cy.contains('label', 'Enable').click();
		cy.contains('button', 'Save and sync now').click();

		cy.wait('@apiRequest');

		cy.on('window:confirm', () => true);

		cy.contains('.components-button', 'Log').click();
		cy.get('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') }).as(
			'syncMessages',
		);
		cy.get('@syncMessages', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Mapping sent',
		);
		cy.get('@syncMessages', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			'Sync complete',
		);
		// check that the number of approved comments is the same as the default.
		cy.get('@syncMessages', { timeout: Cypress.config('elasticPressIndexTimeout') }).should(
			'contain.text',
			`Number of comments indexed: ${defaultApprovedComments}`,
		);

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'comments');
	});

	it('Can not sync anonymous comments until it is approved manually', () => {
		cy.login();
		cy.maybeEnableFeature('comments');

		// enable comments
		cy.visitAdminPage('options-discussion.php');
		cy.get('#require_name_email').uncheck();
		cy.get('#submit').click();

		cy.publishPost({
			title: 'Test Comment',
		});

		cy.logout();

		// publish comment as a logged out user
		cy.visit('/');
		cy.contains('#main .entry-title a', 'Test Comment').first().click();
		cy.get('#comment').type('This is a anonymous comment');
		cy.get('#submit').click();
		cy.wpCli('wp elasticpress sync')
			.its('stdout')
			.should('contain', `Number of comments indexed: ${defaultApprovedComments}`);

		// approve the comment
		cy.visitAdminPage('edit-comments.php?comment_status=moderated');
		cy.intercept('POST', '/wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('.approve a').first().click({ force: true });
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.wpCli('wp elasticpress stats')
			.its('stdout')
			.should('contain', `Documents:  ${defaultApprovedComments + 1}`);

		// trash the comment
		cy.visitAdminPage('edit-comments.php?comment_status=approved');
		cy.intercept('POST', '/wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('.column-comment .trash a').first().click({ force: true });
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		cy.wpCli('wp elasticpress stats')
			.its('stdout')
			.should('contain', `Documents:  ${defaultApprovedComments}`);
	});

	it('Can sync woocommerce reviews', () => {
		cy.login();
		cy.activatePlugin('woocommerce');
		cy.maybeEnableFeature('comments');
		cy.maybeEnableFeature('woocommerce');

		// enable product reviews.
		cy.visit('product/awesome-aluminum-shoes/');
		cy.get('#wp-admin-bar-edit a').click();
		cy.get('.advanced_options.advanced_tab').click();
		cy.get('#comment_status').check();
		cy.get('#publish').click();

		// visit product page and leave a review
		cy.get('#wp-admin-bar-view a').click();
		cy.get('#tab-title-reviews a').click();
		cy.get('.comment-form-rating .star-4').click();
		cy.get('#comment').type('This is a test review');
		cy.get('#submit').click();

		// Check if the new comment was indexed
		cy.refreshIndex('comment').then(() => {
			cy.wpCli('wp elasticpress stats')
				.its('stdout')
				.should('contain', `Documents:  ${defaultApprovedComments + 1}`);
		});

		// trash the review
		cy.wpCli('plugin get woocommerce --field=version').then((wpCliResponse) => {
			const wcVersion = wpCliResponse.stdout;
			if (wcVersion === '6.4.0') {
				cy.visitAdminPage('edit-comments.php?comment_type=review&comment_status=approved');
			} else {
				cy.visitAdminPage(
					'edit.php?post_type=product&page=product-reviews&comment_status=approved',
				);
			}
			cy.get('.column-comment .trash a').first().click({ force: true });
		});

		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	it('Can sync anonymous comments when settings are disabled', () => {
		cy.login();
		cy.maybeEnableFeature('comments');

		cy.visitAdminPage('options-discussion.php');

		// disable settings.
		cy.get('#require_name_email').uncheck();
		cy.get('#comment_moderation').uncheck();
		cy.get('#comment_previously_approved').uncheck();
		cy.get('#submit').click();

		cy.logout();

		// publish comment as a logged out user
		cy.visit('/');
		cy.contains('#main .entry-title a', 'Test Comment').first().click();
		cy.get('#comment').type('This is a anonymous comment');
		cy.get('#submit').click();

		cy.wpCli('wp elasticpress stats')
			.its('stdout')
			.should('contain', `Documents:  ${defaultApprovedComments + 1}`);

		// trash the comment
		cy.visitAdminPage('edit-comments.php?comment_status=approved');
		cy.get('.column-comment .trash a').first().click({ force: true });
	});
});
