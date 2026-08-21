import { test as setup } from '@playwright/test';
import { setDefaultFeatureSettings } from '../utils.js';

setup('Setup global variables', async () => {
	const wpCliRespObj = await setDefaultFeatureSettings();

	process.env.EP_INDEX_NAMES = wpCliRespObj.indexNames;
	process.env.EP_IS_EPIO = wpCliRespObj.isEpIo === 1 ? '1' : '0';
	process.env.WP_VERSION = wpCliRespObj.wpVersion;

	process.env.EP_INDEX_TIMEOUT = wpCliRespObj.isEpIo === 1 ? '120000' : '30000';
});
