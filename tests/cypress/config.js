const { defineConfig } = require('cypress');

module.exports = defineConfig({
	fixturesFolder: 'tests/cypress/fixtures',
	screenshotsFolder: 'tests/cypress/screenshots',
	videosFolder: 'tests/cypress/videos',
	downloadsFolder: 'tests/cypress/downloads',
	video: false,
	retries: {
		runMode: 1,
	},
	elasticPressIndexTimeout: 100000,
	pluginName: 'plugins',
	e2e: {
		// We've imported your old cypress plugins here.
		// You may want to clean this up later by importing these.
		async setupNodeEvents(on, config) {
			/* eslint-disable global-require */
			const path = require('path');
			const { readConfig } = require('@wordpress/env/lib/config');
			/* eslint-enable global-require */

			const wpEnvConfig = await readConfig('wp-env');

			if (wpEnvConfig) {
				const port = wpEnvConfig.env.tests.port || null;

				if (port) {
					config.baseUrl = wpEnvConfig.env.tests.config.WP_SITEURL;
				}
			}

			// Account for ElasticPress and elasticpress usages.
			config.pluginName = path.resolve(`${process.cwd()}../../../`).split('/').pop();

			return config;
		},
		specPattern: 'tests/cypress/integration/**/*.cy.{js,jsx,ts,tsx}',
		supportFile: 'tests/cypress/support/index.js',
		baseUrl: 'http://localhost:8889/',
	},
});
