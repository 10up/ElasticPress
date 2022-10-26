describe('Post Search Feature - Weighting Functionality', () => {
	it("Can't find a post by title if title is not marked as searchable", () => {
		cy.login();

		cy.updateWeighting();

		cy.publishPost({
			title: 'supercustomtitle',
		});

		cy.visit('/?s=supercustomtitle');
		cy.get('.hentry').should('contain.text', 'supercustomtitle');

		cy.visitAdminPage('admin.php?page=elasticpress-weighting');

		cy.contains('.ep-weighting-post-type', 'Posts')
			.contains('.ep-weighting-field', 'Title')
			.find('input[type="checkbox"]')
			.as('postTitleCheckbox');

		cy.get('@postTitleCheckbox').uncheck();

		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.get('button.is-primary').click();
		cy.wait('@apiRequest');

		cy.visit('/?s=supercustomtitle');
		cy.get('.hentry').should('not.exist');

		// Reset setting.
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('@postTitleCheckbox').check();
		cy.get('button.is-primary').click();
		cy.wait('@apiRequest');
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

		cy.contains('.ep-weighting-post-type', 'Posts')
			.contains('.ep-weighting-field', 'Title')
			.find('input[type="number"]')
			.as('postTitleWeight');

		cy.get('@postTitleWeight').clearThenType('20');

		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.get('button.is-primary').click();
		cy.wait('@apiRequest');

		cy.visit('/?s=findbyweighting');
		cy.contains(
			'.site-content article:nth-of-type(1) h2',
			'test weighting title findbyweighting',
		).should('exist');

		// Reset setting.
		cy.visitAdminPage('admin.php?page=elasticpress-weighting');
		cy.get('@postTitleWeight').clearThenType('1');

		cy.intercept('/wp-json/elasticpress/v1/weighting*').as('apiRequest');
		cy.get('button.is-primary').click();
		cy.wait('@apiRequest');
	});
});
