describe('Custom Results', () => {
	before(() => {
		cy.login();
		cy.visitAdminPage('edit.php?post_type=ep-pointer');

		// element doesn't exist where there are no custom results
		cy.get('body').then(($body) => {
			if ($body.find('#bulk-action-selector-top').length > 0) {
				cy.get('#cb-select-all-1').click();
				cy.get('#bulk-action-selector-top').select('trash');
				cy.get('#doaction').click();

				cy.visitAdminPage('edit.php?post_status=trash&post_type=ep-pointer');
				cy.get('.tablenav.top #delete_all').click();
			}
		});
	});

	it('Can change post position and verify the result on search', () => {
		const searchResult = [];
		const searchTerm = 'Feature';

		cy.login();
		cy.visitAdminPage('post-new.php?post_type=ep-pointer');
		cy.intercept('GET', 'wp-json/elasticpress/v1/pointer_preview*').as('ajaxRequest');

		cy.get('#titlewrap input').type(searchTerm);
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// change the position of the post
		cy.dragAndDrop(
			'.pointers .pointer:first-of-type .dashicons-menu',
			'.pointers .pointer:last-of-type .dashicons-menu',
		).then(() => {
			// save the posts pointer in a list
			cy.get('.pointers .pointer .title').each((post) => {
				cy.wrap(post)
					.invoke('text')
					.then((text) => searchResult.push(text));
			});
			cy.get('#publish').click();
		});

		cy.visit(`?s=${searchTerm}`);

		// verify the result of the search is in the same position.
		cy.get('article .entry-title').each((post, index) => {
			cy.wrap(post).invoke('text').should('eq', searchResult[index]);
		});
	});

	it('Can include the new post in search result and verify the result on search', () => {
		const searchResult = [];
		const searchTerm = 'Content';

		cy.visitAdminPage('post-new.php?post_type=ep-pointer');
		cy.intercept('GET', 'wp-json/elasticpress/v1/pointer_preview*').as('ajaxRequest');

		cy.get('#titlewrap input').type(searchTerm);
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		cy.intercept('GET', 'wp-json/elasticpress/v1/pointer_search*').as('ajaxRequest');

		// search for the post.
		cy.get('.search-pointers').type('hello world');
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// add the post to the search result.
		cy.get('.pointer-result:first-of-type .dashicons-plus.add-pointer').click();
		cy.get('.pointers .pointer .title').each((post) => {
			cy.wrap(post)
				.invoke('text')
				.then((text) => searchResult.push(text));
		});

		cy.get('#publish').click();

		cy.visit(`?s=${searchTerm}`);

		// verify the result of the search is in the same position.
		cy.get('article .entry-title').each((post, index) => {
			cy.wrap(post).invoke('text').should('eq', searchResult[index]);
		});
	});
});
