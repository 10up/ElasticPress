describe('WooCommerce Feature', () => {
	before(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	after(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	it('Can auto-activate the feature', () => {
		cy.login();

		cy.activatePlugin('woocommerce');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce').should('have.class', 'feature-active');
	});

	it('Can automatically start a sync if activate the feature', () => {
		cy.login();

		cy.maybeDisableFeature('woocommerce');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce .settings-button').click();
		cy.get('.ep-feature-woocommerce [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-woocommerce .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'woocommerce');
	});

	it('Can fetch orders from Elasticsearch', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		// this is required to sync the orders to Elasticsearch.
		cy.wpCli('elasticpress index --setup --yes');

		cy.visitAdminPage('edit.php?post_type=shop_order');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can fetch products from Elasticsearch in WP Dashboard', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=product');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can fetch products from Elasticsearch in product category archives', () => {
		cy.login();

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/product-category/uncategorized');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can fetch products from Elasticsearch in product rivers', () => {
		cy.login();

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/shop/?filter_size=small');
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);
	});

	it('Can search orders from ElasticPress in WP Dashboard', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=shop_order');

		cy.get('#post-search-input').type('1988');
		cy.get('#search-submit').click();

		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'Query Response Code: HTTP 200',
		);

		cy.get('.order_number .order-view').should('contain.text', '#1988 Mozelle Davis');
	});

	it('Can a user only see his orders on the My Account Order page', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visit('my-account/orders');

		cy.get('.woocommerce-orders-table tbody tr').should('have.length', 3);

		// Test orderby parameter set to date in query.
		cy.get('#debug-menu-target-EP_Debug_Bar_ElasticPress .ep-query-debug').should(
			'contain.text',
			'["orderby"]=>\n  string(4) "date"\n',
		);
	});
});
