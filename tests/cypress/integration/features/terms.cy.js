// eslint-disable-next-line jest/valid-describe-callback
describe('Terms Feature', { tags: '@slow' }, () => {
	const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing'];

	before(() => {
		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');
		cy.activatePlugin('show-comments-and-terms', 'wpCli');

		/**
		 * Delete all tags.
		 */
		tags.forEach((tag) => {
			cy.wpCli(
				`wp term delete post_tag $(wp term get post_tag -s='${tag}' --field=ids)`,
				true,
			);
		});
	});

	it('Can turn the feature on', () => {
		cy.login();

		cy.maybeDisableFeature('terms');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-terms .settings-button').click();
		cy.get('.ep-feature-terms [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-terms .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.contains('.components-button', 'Log').click();
		cy.get('.ep-sync-messages', { timeout: Cypress.config('elasticPressIndexTimeout') })
			.should('contain.text', 'Mapping sent')
			.should('contain.text', 'Sync complete');

		cy.wpCli('wp elasticpress list-features').its('stdout').should('contain', 'terms');
	});

	it('Can search a term in the admin dashboard using Elasticsearch', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

		const searchTerm = 'search term';
		cy.createTerm({ name: searchTerm });

		cy.get('#tag-search-input').type(searchTerm);
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr')
			.should('have.length', 1)
			.should('contain.text', searchTerm);

		// make sure elasticsearch result does contain the term.
		cy.get(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		).should('contain.text', searchTerm);

		// Delete the term
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });
	});

	it('Can a term be removed from the admin dashboard after deleting it', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

		// Create a new term
		const term = 'amazing term';
		cy.createTerm({ name: term });

		// Search for the term
		cy.get('#tag-search-input').type(term);
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody tr').should('have.length', 1).should('contain.text', term);

		// make sure elasticsearch result does contain the term.
		cy.get(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		).should('contain.text', term);

		// Delete the term
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });

		/**
		 * Give Elasticsearch some time. Apparently, if we search again it returns the outdated data.
		 *
		 * @see https://github.com/10up/ElasticPress/issues/2726
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);

		// Re-search for the term and make sure it's not there.
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody').should('contain.text', 'No categories found');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can return a correct tag on searching a tag in admin dashboard', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');

		// create tags.
		tags.forEach((tag) => {
			cy.createTerm({ name: tag, taxonomy: 'post_tag' });
		});

		// search for the tag.
		cy.get('#tag-search-input').type('the most fun thing');
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr .row-title').should('contain.text', 'The Most Fun Thing');

		cy.get(
			'#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug .ep-query-result',
		).should('contain.text', 'The Most Fun Thing');
	});

	it('Can update a child term when a parent term is deleted', () => {
		cy.login();
		cy.maybeEnableFeature('terms');

		const parentTerm = 'bar-parent';
		const childTerm = 'baz-child';

		cy.createTerm({ name: parentTerm });
		cy.createTerm({ name: childTerm, parent: parentTerm });

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
