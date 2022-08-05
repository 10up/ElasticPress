describe('Terms Feature', () => {
	it('Can turn the feature on', () => {
		cy.login();

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-terms .settings-button').click();
		cy.get('.ep-feature-terms [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-terms .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('wp elasticpress list-features').its('stdout').should('contain', 'terms');
	});

	it('Can searching a term in the admin dashboard use Elasticsearch when the Protected Content feature is enabled', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.maybeEnableFeature('protected_content');

		cy.visitAdminPage('edit-tags.php?taxonomy=category');

		const searchTerm = 'classic';
		cy.get('#tag-search-input').type(searchTerm);
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr')
			.should('have.length', 1)
			.should('contain.text', searchTerm);

		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can a term be removed from the admin dashboard after deleting it', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.maybeEnableFeature('protected_content');

		cy.visitAdminPage('edit-tags.php?taxonomy=category');

		const term = 'amazing';

		// Create a new term
		cy.get('#tag-name').type(term);
		cy.intercept('POST', 'wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('#submit').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// Search for the term
		cy.get('#tag-search-input').type(term);
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody tr').should('have.length', 1).should('contain.text', term);

		// Delete the term
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });

		// Re-search for the term and make sure it's not there.
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody').should('contain.text', 'No categories found');
	});

	it('Can return a correct tag on searching a tag in admin dashboard', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.maybeEnableFeature('protected_content');

		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');

		const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing'];

		cy.intercept('POST', 'wp-admin/admin-ajax.php*').as('ajaxRequest');

		// create tags.
		tags.forEach((tag) => {
			cy.get('#tag-name').type(tag);
			cy.get('#submit').click();
			cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);
		});

		// search for the tag.
		cy.get('#tag-search-input').type('the most fun thing');
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr .row-title')
			.should('have.length', 1)
			.should('contain.text', 'The Most Fun Thing');

		// delete the tags.
		tags.forEach((tag) => {
			cy.get('#tag-search-input').clear().type(tag);
			cy.get('#search-submit')
				.click()
				.then(() => {
					cy.get('.wp-list-table tbody tr')
						.first()
						.find('.row-actions .delete a')
						.click({ force: true });
				});
		});
	});
});
