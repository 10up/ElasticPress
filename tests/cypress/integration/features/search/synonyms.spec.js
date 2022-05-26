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
			cy.contains('.button', 'Add Set').click();
			cy.get('.components-form-token-field__input').type(`${word1}{enter}${word2}{enter}`);
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
			cy.contains('.button', 'Add Alternative').click();
			cy.get('.ep-synonyms__input').type(word1);
			cy.get('.components-form-token-field__input').type(`${word2}{enter}`);
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
	it('Can use the Advanced Text Editor', () => {
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.contains('.page-title-action', 'Switch to Advanced Text Editor').click();
		cy.get('#ep-synonym-input').clearThenType(`{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		{enter}
		foo => bar
		test =>
		list,of,words
		`);
		cy.get('#synonym-root .button-primary').click();

		cy.contains(
			'.synonym-solr-editor__validation',
			'Alternatives must have both a primary term and at least one alternative term.',
		).should('exist');

		cy.get('#ep-synonym-input').clearThenType('foo => bar{enter}list,of,words');
		cy.get('#synonym-root .button-primary').click();
		cy.contains('.notice-success', 'Successfully updated synonym filter.').should('exist');

		cy.contains('.page-title-action', 'Switch to Visual Editor').click();
		cy.contains('.synonym-set-editor .components-form-token-field span', 'list').should(
			'exist',
		);
		cy.contains('.synonym-set-editor .components-form-token-field span', 'of').should('exist');
		cy.contains('.synonym-set-editor .components-form-token-field span', 'words').should(
			'exist',
		);
		cy.get('.synonym-alternative-editor input[value="foo"]').should('exist');
		cy.contains('.synonym-alternative-editor .components-form-token-field span', 'bar').should(
			'exist',
		);
	});
	it('Can preserve synonyms if a sync is performed', () => {
		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('.page-title-action').then(($button) => {
			if ($button.text() === 'Switch to Advanced Text Editor') {
				$button.click();
			}
		});

		cy.get('#ep-synonym-input').clearThenType('foo => bar{enter}list,of,words');
		cy.get('#synonym-root .button-primary').click();

		cy.wpCli('elasticpress index --setup --yes');

		cy.visitAdminPage('admin.php?page=elasticpress-synonyms');
		cy.get('#ep-synonym-input')
			.should('contain', 'foo => bar')
			.should('contain', 'list, of, words');
	});
});
