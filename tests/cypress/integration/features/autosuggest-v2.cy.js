describe('ElasticPress Autosuggest V2', () => {
	before(() => {
		cy.task('log', '---------------------- TEST SETUP BEGINS ----------------------');

		// Feature management - disable conflicting features, enable autosuggest-v2
		cy.task('log', 'Disabling features and enabling autosuggest-v2');
		cy.maybeDisableFeature('instant-results');
		cy.maybeDisableFeature('autosuggest');
		cy.maybeEnableFeature('autosuggest-v2');

		// Login to WordPress admin
		cy.login();
	});

	it('Should display results with custom formatting applied via hooks', () => {
		cy.task('log', '---------------------- TEST EXECUTION BEGINS ----------------------');

		// Intercept and handle the ep_autosuggest_loaded event by injecting scripts before page load
		cy.intercept(
			{
				url: '/',
				middleware: true,
			},
			(req) => {
				req.on('response', (res) => {
					// Add a script to the page to inject hooks before EP Autosuggest initializes
					res.body = res.body.replace(
						'</head>',
						`
		  <script>
			(function() {
			  // Create the style element for our custom UI
			  const style = document.createElement('style');
			  style.textContent = \`
				/* Custom styles for autosuggest */
				.custom-header {
				  background: #f0f5fa;
				  padding: 10px 15px;
				  border-bottom: 1px solid #ddd;
				  margin-bottom: 10px;
				}
				
				.custom-header h3 {
				  margin: 0;
				  font-size: 16px;
				  color: #2c3e50;
				}
				
				.suggestion-group {
				  margin-bottom: 15px;
				}
				
				.group-title {
				  font-size: 14px;
				  text-transform: uppercase;
				  color: #7f8c8d;
				  margin: 0 0 8px 15px;
				  font-weight: 600;
				}
				
				.custom-suggestion.active,
				.custom-suggestion:hover {
				  background-color: #eef2f7;
				}
				
				.suggestion-content {
				  display: flex;
				  align-items: center;
				}
				
				.suggestion-text {
				  flex: 1;
				}
				
				.custom-view-all {
				  display: block;
				  width: 100%;
				  background: #f5f8fa;
				  border: none;
				  padding: 8px;
				  text-align: center;
				  cursor: pointer;
				  font-size: 13px;
				  color: #3498db;
				  border-top: 1px solid #ddd;
				}
			  \`;
			  document.head.appendChild(style);
			  
			  // Flag to verify our hooks are working
			  window.customHooksRegistered = false;
			  
			  // Register our hooks before EP Autosuggest initializes
			  document.addEventListener('DOMContentLoaded', function() {
				console.log('[Cypress Test] DOM loaded, preparing to register hooks');
				
				// We need to ensure wp.hooks is available
				const hookCheckInterval = setInterval(function() {
				  if (window.wp && window.wp.hooks) {
					clearInterval(hookCheckInterval);
					console.log('[Cypress Test] wp.hooks is available, registering hooks');
					
					// Register our custom suggestion list hook
					window.wp.hooks.addFilter(
					  'ep.Autosuggest.suggestionList',
					  'cypress-test/custom-suggestion-list',
					  function(props) {
						console.log('[Cypress Test] Hook ep.Autosuggest.suggestionList applied', props);
						
						window.customHooksRegistered = true;
						
						// Return a modified props object with our custom renderer
						return {
						  ...props,
						  renderSuggestionList: function() {
							// Extract props we need
							const {
							  suggestions,
							  activeIndex,
							  onItemClick,
							  SuggestionItemTemplate,
							  showViewAll,
							  onViewAll,
							  expanded,
							} = props;
							
							// Group suggestions by type
							const groupedSuggestions = {};
							suggestions.forEach(function(suggestion) {
							  const type = suggestion.type || 'other';
							  if (!groupedSuggestions[type]) {
								groupedSuggestions[type] = [];
							  }
							  groupedSuggestions[type].push(suggestion);
							});
							
							// Build our custom element
							const header = window.wp.element.createElement(
							  'div',
							  { className: 'custom-header', 'data-testid': 'custom-header' },
							  window.wp.element.createElement(
								'h3',
								null,
								'Search Results (' + suggestions.length + ')'
							  )
							);
							
							// Create groups
							const groups = [];
							for (const type in groupedSuggestions) {
							  if (groupedSuggestions.hasOwnProperty(type)) {
								const items = groupedSuggestions[type];
								
								// Create items for this group
								const itemElements = [];
								for (let i = 0; i < items.length; i++) {
								  const suggestion = items[i];
								  itemElements.push(
									window.wp.element.createElement(SuggestionItemTemplate, {
									  key: suggestion.id,
									  suggestion: suggestion,
									  isActive: suggestions.indexOf(suggestion) === activeIndex,
									  onClick: function() { 
										onItemClick(suggestions.indexOf(suggestion));
									  }
									})
								  );
								}
								
								// Create group element
								groups.push(
								  window.wp.element.createElement(
									'div',
									{ 
									  className: 'suggestion-group', 
									  key: type,
									  'data-testid': 'suggestion-group-' + type
									},
									[
									  window.wp.element.createElement(
										'h4',
										{ className: 'group-title' },
										type.charAt(0).toUpperCase() + type.slice(1) + 's'
									  ),
									  window.wp.element.createElement(
										'ul',
										{ className: 'group-items', role: 'listbox' },
										itemElements
									  )
									]
								  )
								);
							  }
							}
							
							// Create view all button if needed
							let viewAllButton = null;
							if (showViewAll) {
							  viewAllButton = window.wp.element.createElement(
								'button',
								{
								  className: 'custom-view-all',
								  onClick: onViewAll,
								  type: 'button',
								},
								expanded ? 'Show Less' : 'Show All Results'
							  );
							}
							
							// Assemble all elements
							const children = [header, ...groups];
							if (viewAllButton) {
							  children.push(viewAllButton);
							}
							
							return window.wp.element.createElement(
							  'div',
							  { className: 'custom-suggestion-list' },
							  children
							);
						  }
						};
					  },
					  5
					);
					
					// Register our custom suggestion item hook
					window.wp.hooks.addFilter(
					  'ep.Autosuggest.suggestionItem',
					  'cypress-test/custom-suggestion-item',
					  function(props, originalSuggestion) {
						console.log('[Cypress Test] Hook ep.Autosuggest.suggestionItem applied');
						
						if (!originalSuggestion || !originalSuggestion._source) {
						  return props;
						}
						
						// Return modified props
						return {
						  ...props,
						  renderSuggestion: function() {
							const { suggestion, isActive, onClick } = props;
							
							return window.wp.element.createElement(
							  'li',
							  {
								className: 'custom-suggestion ' + (isActive ? 'active' : ''),
								role: 'option',
								'aria-selected': isActive,
								id: 'suggestion-' + suggestion.id,
								onMouseDown: onClick,
								tabIndex: -1,
								'data-testid': 'custom-suggestion-item'
							  },
							  window.wp.element.createElement(
								'a',
								{ href: suggestion.url },
								window.wp.element.createElement(
								  'div',
								  { className: 'suggestion-content' },
								  [
									suggestion.thumbnail ?
									  window.wp.element.createElement('img', {
										src: suggestion.thumbnail,
										alt: '',
										className: 'thumb',
									  }) : null,
									window.wp.element.createElement(
									  'div',
									  { className: 'suggestion-text' },
									  [
										window.wp.element.createElement(
										  'strong',
										  null,
										  'Title Here!!! : ' + suggestion.title
										),
										suggestion.excerpt ?
										  window.wp.element.createElement('p', null, suggestion.excerpt) : null,
										suggestion.type ?
										  window.wp.element.createElement(
											'span',
											{ className: 'content-type' },
											suggestion.type
										  ) : null
									  ]
									)
								  ]
								)
							  )
							);
						  }
						};
					  },
					  5
					);
					
					console.log('[Cypress Test] Hooks registration complete');
				  }
				}, 100);
			  });
			})();
		  </script>
		  </head>`,
					);
				});
			},
		);

		// Visit the page with our intercepted code
		cy.visit('/');

		// Wait for page to load
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(2000);

		// Verify search input exists before proceeding
		cy.get('.wp-block-search__input')
			.should('exist')
			.then(() => {
				cy.task('log', 'Search input found, will type search term');

				// Type in the search input to trigger autosuggest
				// eslint-disable-next-line cypress/unsafe-to-chain-command
				cy.get('.wp-block-search__input').clear().type('blog', { delay: 100 });

				// Check if our hooks were actually registered
				cy.window().then((win) => {
					cy.task('log', `Hooks registered: ${win.customHooksRegistered ? 'Yes' : 'No'}`);
				});

				// Check for autosuggest dropdown visibility
				cy.task('log', 'Checking for autosuggest dropdown visibility');
				cy.get('.ep-autosuggest', { timeout: 10000 })
					.should('be.visible')
					.then(() => {
						cy.task('log', 'Autosuggest dropdown is visible');
					});

				// Check for our custom header
				cy.task('log', 'Checking for custom header in dropdown');
				cy.get('.custom-header, [data-testid="custom-header"]')
					.should('exist')
					.then(($el) => {
						cy.task('log', `Custom header found: ${$el.length} elements`);
						if ($el.length > 0) {
							cy.task('log', `Header text: "${$el.text()}"`);
						}
					});

				// Check for our suggestion groups
				cy.task('log', 'Checking for suggestion group in dropdown');
				cy.get('.suggestion-group, [data-testid^="suggestion-group"]')
					.should('exist')
					.then(($el) => {
						cy.task('log', `Suggestion group(s) found: ${$el.length}`);
					});
			});
	});
});
