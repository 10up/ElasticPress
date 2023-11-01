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
	e2e: {
		async setupNodeEvents(on, config) {
			/* eslint-disable global-require */
			require('@cypress/grep/src/plugin')(config);
			const path = require('path');
			const { loadConfig } = require('@wordpress/env/lib/config');
			/* eslint-enable global-require */

			const configPath = path.resolve('../../');
			const wpEnvConfig = await loadConfig(configPath);

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
		env: {
			grepFilterSpecs: true,
			grepOmitFiltered: true,
		},
		specPattern: 'tests/cypress/integration/**/*.cy.{js,jsx,ts,tsx}',
		supportFile: 'tests/cypress/support/index.js',
	},
});
