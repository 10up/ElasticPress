const fs = require('fs');
const { defineConfig } = require('cypress');

module.exports = defineConfig({
	chromeWebSecurity: false,
	fixturesFolder: 'tests/cypress/fixtures',
	screenshotsFolder: 'tests/cypress/screenshots',
	videosFolder: 'tests/cypress/videos',
	downloadsFolder: 'tests/cypress/downloads',
	video: true,
	videoCompression: true,
	retries: {
		runMode: 1,
	},
	elasticPressIndexTimeout: 100000,
	e2e: {
		async setupNodeEvents(on, config) {
			on('after:spec', (spec, results) => {
				if (results && results.video) {
					// Do we have failures for any retry attempts?
					const failures = results.tests.some((test) =>
						test.attempts.some((attempt) => attempt.state === 'failed'),
					);
					if (!failures) {
						// delete the video if the spec passed and no tests retried
						fs.unlinkSync(results.video);
					}
				}
			});

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
