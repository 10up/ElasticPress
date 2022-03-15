describe('Post Search Feature - Synonyms Functionality', () => {
	before(() => {
		cy.wpCli("wp post list --post_type='ep-synonym' --format=ids", true).then(
			(wpCliResponse) => {
				if (wpCliResponse.code === 0) {
					cy.wpCli(`wp post delete ${wpCliResponse.stdout} --force`, true);
				}
			},
		);
		cy.wpCli(
			"wp post list --s='Testing Synonyms' --ep_integrate='false' --format=ids",
			true,
		).then((wpCliResponse) => {
			if (wpCliResponse.code === 0) {
				cy.wpCli(`wp post delete ${wpCliResponse.stdout} --force`, true);
			}
		});

		cy.login();

		const postsData = [
			{
				title: 'Testing Synonyms - Shoes',
			},
			{
				title: 'Testing Synonyms - Sneakers',
			},
		];
		postsData.forEach((postData) => {
			cy.publishPost(postData);
		});
	});
	it('Can create, search, and delete synonyms sets', () => {
		cy.login();

		// Add the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-sets-editor .synonym__remove').click();
		cy.contains('.synonym-sets-editor .button', 'Add Set').as('addset').click();
		cy.get('.synonym-sets-editor .ep-synonyms__linked-multi-input').type(
			'sneakers{enter}shoes{enter}',
		);
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit('/?s=sneakers');
		cy.contains('.site-content article h2', 'Shoes').should('exist');

		// Remove the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-sets-editor .synonym__remove').click();
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit('/?s=sneakers');
		cy.contains('.site-content article h2', 'Shoes').should('not.exist');
	});
});
