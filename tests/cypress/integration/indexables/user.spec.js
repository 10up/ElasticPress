describe('User Indexable', () => {
	function createUser(userData) {
		const newUserData = {
			userLogin: 'testuser',
			userEmail: 'testuser@example.com',
			...userData,
		};

		cy.wpCli(`wp user get ${newUserData.userLogin} --field=ID`, true).then((wpCliResponse) => {
			if (wpCliResponse.code === 0) {
				cy.wpCli(`wp user delete ${newUserData.userLogin} --yes --network`);
				cy.wpCli('wp elasticpress index --setup --yes');
			}
		});

		cy.visitAdminPage('user-new.php');
		cy.get('#user_login').clearThenType(newUserData.userLogin);
		cy.get('#email').clearThenType(newUserData.userEmail);
		cy.get('#noconfirmation').check();
		cy.get('#createusersub').click();
		cy.get('#message').should('be.visible');
	}

	function searchUser(userName = 'testuser') {
		cy.visitAdminPage('users.php');
		cy.get('#user-search-input').clearThenType(userName);
		cy.get('#search-submit').click();
	}

	after(() => {
		cy.maybeDisableFeature('users');
	});

	it('Can automatically start a sync if activate the feature', () => {
		cy.login();

		cy.maybeDisableFeature('users');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-users .settings-button').click();
		cy.get('#feature_active_users_enabled').click();
		cy.get('a.save-settings[data-feature="users"]').click();
		cy.on('window:confirm', () => {
			return true;
		});

		// Give it up to a minute to sync.
		cy.get('.sync-status', { timeout: 60000 }).should('contain.text', 'Sync complete');

		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.wpCli('elasticpress list-features').then((wpCliResponse) => {
			expect(wpCliResponse.stdout).to.contains('users');
		});
	});

	it('Can run a simple user sync', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		createUser();

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('.ep-query-debug').should('contain.text', 'Query Response Code: HTTP 200');
		cy.get('.query-results').should('contain.text', '"user_email": "testuser@example.com"');

		// Test if the user is still found a reindex.
		cy.wpCli('wp elasticpress index --setup --yes');

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('.ep-query-debug').should('contain.text', 'Query Response Code: HTTP 200');
		cy.get('.query-results').should('contain.text', '"user_email": "testuser@example.com"');
	});

	it('Can sync user meta data', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		createUser();

		searchUser();

		cy.get('#the-list .column-username .edit a').click({ force: true });
		cy.get('#first_name').clearThenType('John');
		cy.get('#last_name').clearThenType('Doe');
		cy.get('#submit').click();

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('.ep-query-debug').should('contain.text', 'Query Response Code: HTTP 200');
		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.get('.query-results')
			.invoke('text')
			.then((text) => {
				expect(text).to.contain('"user_email": "testuser@example.com"');
				expect(text).to.contain('"value": "John"');
				expect(text).to.contain('"value": "Doe"');
			});
	});
});
