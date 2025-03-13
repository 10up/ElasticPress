describe('Status Report', () => {
	beforeEach(() => {
		cy.visitAdminPage('admin.php?page=elasticpress-status-report');
	});

	it('should have a notice component for AJAX reports instead of the content, buttons should not be disabled by default, should load report after click on full report, and generate report should be disabled after clicking it', () => {
		cy.get('#indexable').parent().parent().find('.components-notice__content').should('exist');

		cy.get('#download-report').should('not.be.disabled');
		cy.get('#copy-report').should('not.be.disabled');
		cy.get('#generate-full-report').should('not.be.disabled');

		cy.get('#generate-full-report').click();
		cy.get('#generate-full-report').should('be.disabled');

		cy.get('#indexable')
			.parent()
			.parent()
			.find('.components-notice__content')
			.should('not.exist');

		cy.get('#download-report').should('have.text', 'Download full status report');
		cy.get('#copy-report').should('have.text', 'Copy full status report to clipboard');
	});
});
