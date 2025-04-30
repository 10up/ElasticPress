import { test as setup } from '@playwright/test';
import { defaultFeatures, wpCliEval } from '../utils';

setup('Setup global variables', async () => {
	const wpCliResponse = await wpCliEval(
		`
		\\ElasticPress\\IndexHelper::factory()->clear_index_meta();

		$features = json_decode( '${JSON.stringify(defaultFeatures)}', true );

		$is_epio = (int) \\ElasticPress\\Utils\\is_epio();

		if ( ! $is_epio ) {
			$host            = \\ElasticPress\\Utils\\get_host();
			$host            = str_replace( '172.17.0.1', 'localhost', $host );
			$host            = str_replace( 'host.docker.internal', 'localhost', $host );
			$index_name      = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$as_endpoint_url = $host . $index_name . '/_search';
			
			$features['autosuggest']['endpoint_url'] = $as_endpoint_url;
		}

		update_option( 'ep_feature_settings', $features );

		$index_names = \\ElasticPress\\Elasticsearch::factory()->get_index_names( 'active' );
		echo wp_json_encode(
			[
				'indexNames' => $index_names,
				'isEpIo'     => $is_epio,
				'wpVersion'  => get_bloginfo( 'version' ),
			]
		);
		`,
	);
	const wpCliRespObj = JSON.parse(wpCliResponse);
	process.env.EP_INDEX_NAMES = wpCliRespObj.indexNames;
	process.env.EP_IS_EPIO = wpCliRespObj.isEpIo === 1 ? '1' : '0';
	process.env.WP_VERSION = wpCliRespObj.wpVersion;
});
