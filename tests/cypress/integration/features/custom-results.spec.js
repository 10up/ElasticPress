describe('Custom Results', () => {
	const testPost = 'test-post';

	before(() => {
		cy.wpCli("wp post list --post_type='ep-pointer' --format=ids", true).then(
			(wpCliResponse) => {
				if (wpCliResponse.code === 0) {
					cy.wpCli(`wp post delete ${wpCliResponse.stdout} --force`, true);
				}
			},
		);

		/**
		 * Delete the test post if it exists.
		 *
		 * @todo Replace with WPCLI when dummy data is removed.
		 */
		cy.login();
		cy.visitAdminPage('edit.php');
		cy.get('#post-search-input').type(`${testPost}{enter}`);

		// element doesn't exist when there are no post.
		cy.get('body').then(($body) => {
			if ($body.find('#the-list .has-row-actions').length > 0) {
				cy.get('#cb-select-all-1').click();
				cy.get('#bulk-action-selector-top').select('trash');
				cy.get('#doaction').click();

				cy.visitAdminPage('edit.php?post_status=trash&post_type=post');
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
			// save the posts positions in a list
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
		const searchTerm = 'Custom Page';

		cy.publishPost({
			title: testPost,
		});

		cy.visitAdminPage('post-new.php?post_type=ep-pointer');
		cy.intercept('GET', 'wp-json/elasticpress/v1/pointer_preview*').as('ajaxRequest');

		cy.get('#titlewrap input').type(searchTerm);
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		cy.intercept('GET', 'wp-json/elasticpress/v1/pointer_search*').as('ajaxRequest');

		// search for the post.
		cy.get('.search-pointers').type(testPost);
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// add the post to the search result.
		cy.get('.pointer-result:first-of-type .dashicons-plus.add-pointer').click();

		// save the posts positions in a list
		cy.get('.pointers .pointer .title').each((post) => {
			cy.wrap(post)
				.invoke('text')
				.then((text) => searchResult.push(text));
		});

		cy.intercept('POST', '/wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('#publish').click();
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		cy.visit(`?s=${searchTerm}`);

		// verify the result of the search is in the same position.
		cy.get('article .entry-title').each((post, index) => {
			cy.wrap(post).invoke('text').should('eq', searchResult[index]);
		});
	});
});
