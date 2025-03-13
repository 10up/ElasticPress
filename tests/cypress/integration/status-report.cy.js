describe('Status Report', () => {
	beforeEach(() => {
		cy.visitAdminPage('admin.php?page=elasticpress-status-report');
	});

	it('should have a notice component for AJAX reports instead of the content', () => {
		cy.get('#indexable').parent().parent().find('.components-notice__content').should('exist');
	});

	it('should have all three buttons enabled to click on first load', () => {
		cy.get('#download-report').should('not.be.disabled');
		cy.get('#copy-report').should('not.be.disabled');
		cy.get('#generate-full-report').should('not.be.disabled');
	});

	it('should disable the generate report button after clicking it', () => {
		cy.get('#generate-full-report').click();
		cy.get('#generate-full-report').should('be.disabled');
	});

	it('should replace the notice component with the content after clicking the generate report button', () => {
		cy.get('#generate-full-report').click();
		cy.get('#indexable')
			.parent()
			.parent()
			.find('.components-notice__content')
			.should('not.exist');
	});

	it('should have changed the copy and download button text after clicking on generate full report', () => {
		cy.get('#generate-full-report').click();
		cy.get('#download-report').should('have.text', 'Download full status report');
		cy.get('#copy-report').should('have.text', 'Copy full status report to clipboard');
	});
});
