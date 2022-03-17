describe('Post Search Feature - Synonyms Functionality', () => {
	const word1 = 'authenticity';
	const word2 = 'credibility';

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
				title: `Testing Synonyms - ${word1}`,
			},
			{
				title: `Testing Synonyms - ${word2}`,
			},
		];
		postsData.forEach((postData) => {
			cy.publishPost(postData);
		});
	});
	beforeEach(() => {
		cy.login();
	});
	it('Can create, search, and delete synonyms sets', () => {
		// Add the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-sets-editor').within(() => {
			cy.get('.synonym__remove').click();
			cy.contains('.button', 'Add Set').as('addset').click();
			cy.get('.ep-synonyms__linked-multi-input').type(`${word1}{enter}${word2}{enter}`);
		});
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit(`/?s=${word2}`);
		cy.contains('.site-content article h2', word1).should('exist');

		// Remove the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-sets-editor .synonym__remove').click();
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit(`/?s=${word2}`);
		cy.contains('.site-content article h2', word1).should('not.exist');
	});
	it('Can create, search, and delete synonyms alternatives', () => {
		// Add the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-alternatives-editor').within(() => {
			cy.get('.synonym__remove').click();
			cy.contains('.button', 'Add Alternative').as('addset').click();
			cy.get('.ep-synonyms__input').type(word1);
			cy.get('.ep-synonyms__linked-multi-input').type(`${word2}{enter}`);
		});
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit(`/?s=${word1}`);
		cy.contains('.site-content article h2', word2).should('exist');
		cy.visit(`/?s=${word2}`);
		cy.contains('.site-content article h2', word1).should('not.exist');

		// Remove the set
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.synonym-alternatives-editor .synonym__remove').click();
		cy.get('#synonym-root .button-primary').click();

		// Check if it works
		cy.visit(`/?s=${word1}`);
		cy.contains('.site-content article h2', word2).should('not.exist');
	});
});
