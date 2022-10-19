describe('Post Search Feature - Weighting Functionality', () => {
	it("Can't find a post by title if title is not marked as searchable", () => {
		cy.login();

		cy.publishPost({
			title: 'Test ElasticPress 1',
		});

		cy.visit('/?s=Test+ElasticPress+1');
		cy.get('.hentry').should('contain.text', 'Test ElasticPress 1');

		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('#post-post_title-enabled').click();
		cy.screenshot();
		cy.get('#submit').click();

		cy.visit('/?s=Test+ElasticPress+1');
		cy.get('.hentry').should('not.exist');

		// Reset setting.
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('#post-post_title-enabled').click();
		cy.get('#submit').click();
	});

	it('Can increase post_title weighting and influence search results', () => {
		cy.login();

		const postsData = [
			{
				title: 'test weighting content',
				content: 'findbyweighting findbyweighting findbyweighting',
			},
			{
				title: 'test weighting title findbyweighting',
				content: 'Nothing here.',
			},
		];

		postsData.forEach((postData) => {
			cy.publishPost(postData);
		});

		cy.visit('/?s=findbyweighting');
		cy.contains('.site-content article:nth-of-type(1) h2', 'test weighting content').should(
			'exist',
		);

		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('input[name="weighting[post][post_title][weight]"]').invoke('attr', 'value', '20');
		cy.get('#submit').click();

		cy.visit('/?s=findbyweighting');
		cy.contains(
			'.site-content article:nth-of-type(1) h2',
			'test weighting title findbyweighting',
		).should('exist');

		// Reset setting.
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('input[name="weighting[post][post_title][weight]"]').invoke('attr', 'value', '1');
		cy.get('#submit').click();
	});
});
