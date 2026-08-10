import { test as setup } from '@playwright/test';
import { setDefaultFeatureSettings, wpCli } from '../utils.js';

setup('Setup global variables', async () => {
	const wpCliRespObj = await setDefaultFeatureSettings();

	process.env.EP_INDEX_NAMES = wpCliRespObj.indexNames;
	process.env.EP_IS_EPIO = wpCliRespObj.isEpIo === 1 ? '1' : '0';
	process.env.WP_VERSION = wpCliRespObj.wpVersion;

	process.env.EP_INDEX_TIMEOUT = '30000';

	const esVersion = await wpCli(
		'eval "echo ElasticPress\\Elasticsearch::factory()->get_elasticsearch_version();"',
	);
	process.env.ES_VERSION = esVersion.toString().trim();
});
