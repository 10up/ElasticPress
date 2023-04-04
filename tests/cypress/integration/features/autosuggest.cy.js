describe('Autosuggest Feature', () => {
	before(() => {
		cy.wpCli('elasticpress sync --setup --yes');
	});

	beforeEach(() => {
		cy.deactivatePlugin('custom-headers-for-autosuggest', 'wpCli');
	});

	it('Can see autosuggest list', () => {
		cy.visit('/');

		cy.get('.wp-block-search__input').type('blog');
		cy.get('.ep-autosuggest').should('be.visible').should('contain.text', 'a Blog page');
	});

	it('Can see post in autosuggest list', () => {
		cy.visit('/');

		cy.intercept({
			url: /(_search|autosuggest)$/,
			headers: {
				'X-ElasticPress-Request-ID': /[0-9a-f]{32}$/,
			},
		}).as('apiRequest');

		cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');

		cy.wait('@apiRequest');

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

	it('Can see post in autosuggest list when headers are modified', () => {
		cy.activatePlugin('custom-headers-for-autosuggest', 'wpCli');
		cy.visit('/');

		cy.intercept({
			url: /(_search|autosuggest)$/,
			headers: {
				'X-Custom-Autosuggest-Header': 'MyAutosuggest',
			},
		}).as('apiRequest');

		cy.get('.wp-block-search__input').type('Markup: HTML Tags and Formatting');

		cy.wait('@apiRequest');

		cy.get('.ep-autosuggest').should(($autosuggestList) => {
			// eslint-disable-next-line no-unused-expressions
			expect($autosuggestList).to.be.visible;
			expect($autosuggestList[0].innerText).to.contains('Markup: HTML Tags and Formatting');
		});
	});

	it('Can use autosuggest navigate callback filter', () => {
		cy.activatePlugin('filter-autosuggest-navigate-callback', 'wpCli');

		cy.visit('/');
		cy.get('.wp-block-search__input').type('blog');

		cy.get('.ep-autosuggest li a')
			.first()
			.click()
			.then(() => {
				cy.url().should('include', 'cypress=foobar');
			});
	});
});
