describe('Comments Feature', () => {
	const defaultApprovedComments = 26;

	before(() => {
		cy.visitAdminPage('options-discussion.php');
		cy.get('#require_name_email').check();
		cy.get('#comment_moderation').check();
		cy.get('#comment_previously_approved').check();
		cy.get('#submit').click();
	});

	it('Can automatically start a sync if activate the feature', () => {
		cy.login();

		cy.maybeDisableFeature('comments');

		cy.visitAdminPage('admin.php?page=elasticpress');

		cy.get('.ep-feature-comments .settings-button').click();
		cy.get('.ep-feature-comments [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-comments .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-panel').last().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', 'Mapping sent')
			.should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'comments');
	});

	it('Can only sync approved comments', () => {
		cy.login();
		cy.maybeEnableFeature('comments');

		cy.visitAdminPage('admin.php?page=elasticpress-sync');

		// start sync and test results.
		cy.get('.ep-sync-button--sync').click();
		cy.get('.ep-sync-panel').first().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', `Number of comments indexed: ${defaultApprovedComments}`);
	});

	it('Can not sync anonymous comments', () => {
		cy.login();
		cy.maybeEnableFeature('comments');

		// enable wordpress comments
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

		// start sync and test results.
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--sync').click();
		cy.get('.ep-sync-panel').first().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', {
				timeout: Cypress.config('elasticPressIndexTimeout'),
			})
			.should('contain.text', `Number of comments indexed: ${defaultApprovedComments}`);

		// trash the comment
		cy.visitAdminPage('edit-comments.php?comment_status=moderated');
		cy.get('.column-comment .trash a').first().click({ force: true });
	});

	it('Can sync comments if approved manually', () => {
		cy.login();
		cy.publishPost({
			title: 'Test Post',
		});

		cy.logout();

		// publish comment as a logged out user
		cy.visit('/');
		cy.contains('#main .entry-title a', 'Test Post').first().click();
		cy.get('#comment').type('This is a pending comment');
		cy.get('#author').type('Test Author');
		cy.get('#email').type('test@example.com');
		cy.get('#submit').click();

		cy.visitAdminPage('edit-comments.php');
		cy.maybeEnableFeature('comments');

		// approve the comment
		cy.visitAdminPage('edit-comments.php?comment_status=moderated');
		cy.get('.approve a').first().click({ force: true });

		// start sync and test results.
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--sync').click();
		cy.get('.ep-sync-panel').first().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', {
				timeout: Cypress.config('elasticPressIndexTimeout'),
			})
			.should('contain.text', `Number of comments indexed: ${defaultApprovedComments + 1}`);

		// trash the comment
		cy.visitAdminPage('edit-comments.php?comment_status=approved');
		cy.get('.column-comment .trash a').first().click({ force: true });
	});

	it('Can see ElasticPress - Comment widget in dashboard', () => {
		cy.maybeEnableFeature('comments');
		cy.visitAdminPage('widgets.php');
		cy.get('.widget-title h3').should('contain', 'ElasticPress - Comments');
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

		// start sync and test results.
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--sync').click();
		cy.get('.ep-sync-panel').first().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', `Number of comments indexed: ${defaultApprovedComments + 1}`);

		// trash the review
		cy.visitAdminPage(
			'edit.php?post_type=product&page=product-reviews&comment_status=approved',
		);
		cy.get('.column-comment .trash a').first().click({ force: true });

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

		// create test post.
		cy.publishPost({
			title: 'Test Comment',
		});

		cy.logout();

		// publish comment as a logged out user
		cy.visit('/');
		cy.contains('#main .entry-title a', 'Test Comment').first().click();
		cy.get('#comment').type('This is a anonymous comment');
		cy.get('#submit').click();

		// start sync and test results.
		cy.visitAdminPage('admin.php?page=elasticpress-sync');
		cy.get('.ep-sync-button--sync').click();
		cy.get('.ep-sync-panel').first().as('syncPanel');
		cy.get('@syncPanel').find('.components-form-toggle').click();
		cy.get('@syncPanel')
			.find('.ep-sync-messages', {
				timeout: Cypress.config('elasticPressIndexTimeout'),
			})
			.should('contain.text', `Number of comments indexed: ${defaultApprovedComments + 1}`);

		// trash the comment
		cy.visitAdminPage('edit-comments.php?comment_status=approved');
		cy.get('.column-comment .trash a').first().click({ force: true });
	});
});
