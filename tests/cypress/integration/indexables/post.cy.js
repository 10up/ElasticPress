describe('Post Indexable', () => {
	it('Can conditionally update posts when a term is edited', () => {
		/**
		 * Using the default content here:
		 * - the `Classic` (ID 29) term has 37 posts
		 * - the `Block` (ID 54) term has 11 posts
		 * Important: There is no post with both categories, as that would skew results.
		 */
		cy.setPerIndexCycle();
		cy.visitAdminPage('edit-tags.php?taxonomy=category');
		cy.get('div[data-ep-notice="too_many_posts_on_term"]').should('not.exist');

		cy.setPerIndexCycle(36);
		cy.visitAdminPage('edit-tags.php?taxonomy=category');
		cy.get('div[data-ep-notice="too_many_posts_on_term"]').should('exist');

		// Change the `Classic` term, should not index
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=29');
		cy.get('div[data-ep-notice="edited_single_term"]').should('exist');
		cy.get('#name').clearThenType('totallydifferenttermname');
		cy.get('input.button-primary').click();

		// Change the `Block` term, should index
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=20');
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
		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=29');
		cy.get('#name').clearThenType('Classic');
		cy.get('input.button-primary').click();

		cy.visitAdminPage('term.php?taxonomy=category&tag_ID=20');
		cy.get('#name').clearThenType('Block');
		cy.get('input.button-primary').click();

		cy.setPerIndexCycle();
	});
});
