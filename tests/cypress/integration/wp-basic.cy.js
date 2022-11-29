describe('WordPress basic actions', () => {
	before(() => {
		cy.wpCli('elasticpress sync --setup --yes');
	});

	it('Has <title> tag', () => {
		cy.visit('/');
		cy.get('title').should('exist');
	});

	it('Can login', () => {
		cy.login();
		cy.get('#wpadminbar').should('exist');
	});

	it('Can see admin bar on front end', () => {
		cy.login();
		cy.visit('/');
		cy.get('#wpadminbar').should('exist');
	});

	it('Can save own profile', () => {
		cy.login();
		cy.visitAdminPage('profile.php');
		cy.get('#first_name').clearThenType('Test Name');
		cy.get('#submit').click();
		cy.get('#first_name').should('have.value', 'Test Name');
	});

	it('Can change site title', () => {
		cy.login();
		cy.visitAdminPage('options-general.php');
		cy.get('#wpadminbar').should('be.visible');
		cy.get('#blogname').clearThenType('Updated Title');
		cy.get('#submit').click();
		cy.get('#wp-admin-bar-site-name a').first().should('have.text', 'Updated Title');
	});
});
