describe('Post Indexable', () => {
	it('Can conditionally update posts when a term is edited', () => {
		/**
		 * At this point, using the default content:
		 * - the `Classic` (ID 29) term has 36 posts
		 * - the `Block` (ID 54) term has 7 posts
		 * Important: There is no post with both categories, as that would skew results.
		 */

		// Make sure post categories are searchable.
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('#post-terms\\.category\\.name-enabled').check();
		cy.get('#submit').click();

		cy.setPerIndexCycle();
		cy.visitAdminPage('edit-tags.php?taxonomy=category');
		cy.get('div[data-ep-notice="too_many_posts_on_term"]').should('not.exist');

		cy.setPerIndexCycle(35);
		cy.visitAdminPage('edit-tags.php?taxonomy=category&orderby=count&order=desc');
		cy.get('div[data-ep-notice="too_many_posts_on_term"]').should('exist');

		// Change the `Classic` term, should not index
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=15');
		cy.get('div[data-ep-notice="edited_single_term"]').should('exist');
		cy.get('#name').clearThenType('totallydifferenttermname');
		cy.get('input.button-primary').click();

		// Change the `Block` term, should index
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=6');
		cy.get('div[data-ep-notice="edited_single_term"]').should('not.exist');
		cy.get('#name').clearThenType('b10ck');
		cy.get('input.button-primary').click();

		// Make sure the changes are processed by ES
		cy.refreshIndex('post');

		cy.visit('/?s=totallydifferenttermname');
		cy.get('.hentry').should('not.exist');

		cy.visit('/?s=b10ck');
		cy.get('.hentry').should('exist');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'"name": "b10ck",',
		);

		// Restore
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=15');
		cy.get('#name').clearThenType('Classic');
		cy.get('input.button-primary').click();

		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=6');
		cy.get('#name').clearThenType('Block');
		cy.get('input.button-primary').click();

		cy.setPerIndexCycle();
	});
});
