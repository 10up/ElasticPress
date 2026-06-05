/**
 * WordPress dependencies.
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies.
 */
import ExcludeFromEmbedding from './plugins/exclude-from-embedding';
import IncludeForEmbedding from './plugins/include-for-embedding';

const { postTypeConfig } = window.epEmbeddingsEditor;

const { embeddingMode, embeddable } = postTypeConfig;

if (embeddingMode === 'manual' && embeddable) {
	registerPlugin('ep-embedding-include', {
		render: IncludeForEmbedding,
		icon: null,
	});
}

if (embeddingMode === 'automatic' && embeddable) {
	registerPlugin('ep-embedding-exclude', {
		render: ExcludeFromEmbedding,
		icon: null,
	});
}
