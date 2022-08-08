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

	it('Can searching a term in the admin dashboard use Elasticsearch', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

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

		cy.visitAdminPage('edit-tags.php?taxonomy=category');

		const term = 'amazing';

		// Create a new term
		cy.createTaxonomy({ name: term });

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

		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');

		const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing'];
		// create tags.
		tags.forEach((tag) => {
			cy.createTaxonomy({ name: tag, taxonomy: 'post_tag' });
		});

		// search for the tag.
		cy.get('#tag-search-input').type('the most fun thing');
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr .row-title').should('contain.text', 'The Most Fun Thing');

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

	it('Can a child term be updated when a parent term is deleted', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

		const parentTerm = 'bar-parent';
		const childTerm = 'baz-child';

		cy.createTaxonomy({ name: parentTerm });
		cy.createTaxonomy({ name: childTerm, parent: parentTerm });

		cy.get('#tag-search-input').type(`${parentTerm}{enter}`);

		// delete the parent term.
		cy.intercept('POST', 'wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// make sure the child term parent field is set to none.
		cy.get('#tag-search-input').clear().type(`${childTerm}{enter}`);
		cy.get('.wp-list-table tbody tr .column-primary a').first().click();
		cy.get('#parent').should('have.value', '-1');

		// delete the child term.
		cy.get('#delete-link a').click();
	});
});
