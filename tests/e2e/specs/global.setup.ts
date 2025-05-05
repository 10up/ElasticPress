import { test as setup } from '@playwright/test';
import { resetSettings } from '../utils';

setup('Setup global variables', async () => {
	const wpCliRespObj = await resetSettings();

	process.env.EP_INDEX_NAMES = wpCliRespObj.indexNames;
	process.env.EP_IS_EPIO = wpCliRespObj.isEpIo === 1 ? '1' : '0';
	process.env.WP_VERSION = wpCliRespObj.wpVersion;

	process.env.EP_INDEX_TIMEOUT = '30000';
});
