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

		cy.wpCli(`wp post list --s='${testPost}' --ep_integrate='false' --format=ids`, true).then(
			(wpCliResponse) => {
				if (wpCliResponse.code === 0) {
					cy.wpCli(`wp post delete ${wpCliResponse.stdout} --force`, true);
				}
			},
		);
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
		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.dragAndDrop(
			'.pointers .pointer:first-of-type .dashicons-menu',
			'.pointers .pointer:last-of-type .dashicons-menu',
		).then(() => {
			// save the posts positions in a list
			cy.get('.pointers .pointer .title').each((post) => {
				searchResult.push(post[0].innerText);
			});

			expect(searchResult.length).to.be.gt(0);
			cy.get('#publish').click();

			cy.visit(`?s=${searchTerm}`);

			// verify the result of the search is in the same position.
			cy.get(`article:nth-child(-n+${searchResult.length}) .entry-title`).each(
				(post, index) => {
					expect(post[0].innerText).to.equal(searchResult[index]);
				},
			);
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
		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.get('.pointers .pointer:nth-child(-n+5) .title') // 5 being the number of posts per page, as we will check only the first page.
			.each((post) => {
				searchResult.push(post[0].innerText);
			});
		expect(searchResult.length).to.be.gt(0);
		cy.get('#publish').click();
		/**
		 * Give Elasticsearch some time to update the posts in custom results.
		 */
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(1000);
		cy.visit(`?s=${searchTerm}`);

		// verify the result of the search is in the same position.
		cy.get(`article:nth-child(-n+${searchResult.length}) .entry-title`).each((post, index) => {
			expect(post[0].innerText).to.equal(searchResult[index]);
		});
	});
});
