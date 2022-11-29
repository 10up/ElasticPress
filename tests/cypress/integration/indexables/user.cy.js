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
				cy.wpCli('wp elasticpress sync --setup --yes');
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
		cy.get('.ep-feature-users [name="settings[active]"][value="1"]').click();
		cy.get('.ep-feature-users .button-primary').click();
		cy.on('window:confirm', () => {
			return true;
		});

		cy.get('.ep-sync-progress strong', {
			timeout: Cypress.config('elasticPressIndexTimeout'),
		}).should('contain.text', 'Sync complete');

		cy.wpCli('elasticpress list-features').its('stdout').should('contain', 'users');
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
		cy.wpCli('wp elasticpress sync --setup --yes');

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

	it('Only returns users from the current blog', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		const newUserData = {
			userLogin: 'nobloguser',
			userEmail: 'no-blog-user@example.com',
		};

		cy.wpCli(`wp user get ${newUserData.userLogin} --field=ID`, true).then((wpCliResponse) => {
			if (wpCliResponse.code === 0) {
				cy.wpCli(`wp user delete ${newUserData.userLogin} --yes --network`);
				cy.wpCli('wp elasticpress sync --setup --yes');
			}
		});

		// Create a user without a blog.
		cy.visitAdminPage('network/user-new.php');
		cy.get('#username').clearThenType(newUserData.userLogin);
		cy.get('#email').clearThenType(newUserData.userEmail);
		cy.get('#add-user').click();
		cy.get('#message.updated').should('be.visible');

		// Searching for it should not return anything.
		searchUser('nobloguser');
		cy.get('.wp-list-table').should('contain.text', 'No users found.');
		cy.getTotal(0);
		cy.get('.ep-query-debug').should('contain.text', 'Query Response Code: HTTP 200');

		// Add user to the blog.
		cy.visitAdminPage('user-new.php');
		cy.get('#adduser-email').clearThenType(newUserData.userLogin);
		cy.get('#adduser-noconfirmation').check();
		cy.get('#addusersub').click();
		cy.get('#message.updated').should('be.visible');

		// Searching for it should return it.
		searchUser('nobloguser');
		cy.get('.wp-list-table').should('contain.text', 'nobloguser');
		cy.getTotal(1);
		cy.get('.ep-query-debug').should('contain.text', 'Query Response Code: HTTP 200');
		cy.get('.query-results').should('contain.text', '"user_email": "no-blog-user@example.com"');
	});
});
