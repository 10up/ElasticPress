describe('Related Posts block', () => {
	const blockName = 'Related Posts (ElasticPress)';

	it('Related Posts block is available when feature is enabled', () => {
		cy.maybeEnableFeature('related_posts');
		cy.visitAdminPage('post-new.php');
		cy.getBlocksList().should('contain.text', blockName);
	});

	it('Related Posts block is not available when feature is disabled', () => {
		cy.maybeDisableFeature('related_posts');
		cy.visitAdminPage('post-new.php');
		cy.getBlocksList().should('not.contain.text', blockName);
	});
});
