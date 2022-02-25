describe('Autosuggest Feature', () => {
	before(() => {
		cy.wpCli('elasticpress index --setup --yes');
	});

	it('Can see autosuggest list', () => {
		cy.visit('/');

		cy.get('.wp-block-search__input').type('blog');
		cy.get('.ep-autosuggest').should('be.visible').should('contain.text', 'a Blog page');
	});

	it('Can see post in autosuggest list', () => {
		cy.visit('/');

		cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');
		cy.get('.ep-autosuggest').should(($autosuggestList) => {
			// eslint-disable-next-line no-unused-expressions
			expect($autosuggestList).to.be.visible;
			expect($autosuggestList[0].innerText).to.contains('Markup: HTML Tags and Formatting');
		});
	});

	it('Can find post by category in autosuggest list', () => {
		cy.updateWeighting({
			post: {
				'terms.category.name': {
					weight: 1,
					enabled: true,
				},
			},
		});

		cy.visit('/');

		cy.get('.wp-block-search__input').type('aciform');
		cy.get('.ep-autosuggest')
			.should('be.visible')
			.should('contain.text', 'Keyboard navigation');

		cy.updateWeighting();
	});

	it('Can click on a post in autosuggest', () => {
		cy.visit('/');

		cy.get('.wp-block-search__input').type('blog');
		cy.get('.ep-autosuggest li a')
			.first()
			.click()
			.then(($link) => {
				cy.url().should('eq', $link.prop('href'));
			});
	});
});
