/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';

const initialState = {
	metaKeys: [],
	metaRanges: {},
	taxonomies: {},
};

const reducer = (state, action) => {
	switch (action.type) {
		case 'SET_META_KEYS': {
			return {
				...state,
				metaKeys: action.metaKeys,
			};
		}
		case 'SET_TAXONOMIES': {
			return {
				...state,
				taxonomies: action.taxonomies,
			};
		}
		case 'SET_META_RANGE': {
			return {
				...state,
				metaRanges: {
					...state.metaRanges,
					[action.key]: action.metaRange,
				},
			};
		}
		default: {
			return state;
		}
	}
};

const actions = {
	setMetaKeys(metaKeys) {
		return {
			type: 'SET_META_KEYS',
			metaKeys,
		};
	},
	getMetaKeys() {
		return {
			type: 'GET_META_KEYS',
		};
	},
	setTaxonomies(taxonomies) {
		return {
			type: 'SET_TAXONOMIES',
			taxonomies,
		};
	},
	getTaxonomies() {
		return {
			type: 'GET_TAXONOMIES',
		};
	},
	setMetaRange(key, metaRange) {
		return {
			type: 'SET_META_RANGE',
			key,
			metaRange,
		};
	},
	getMetaRange(key) {
		return {
			type: 'GET_META_RANGE',
			key,
		};
	},
};

const selectors = {
	getMetaKeys(state) {
		const { metaKeys } = state;

		return metaKeys;
	},
	getTaxonomies(state) {
		const { taxonomies } = state;

		return taxonomies;
	},
	getMetaRange(state, key) {
		const {
			metaRanges: { [key]: rangePreview },
		} = state;

		return rangePreview;
	},
};

const controls = {
	GET_META_KEYS() {
		return apiFetch({ path: 'elasticpress/v1/meta-keys' });
	},
	GET_TAXONOMIES() {
		return apiFetch({ path: 'elasticpress/v1/taxonomies' });
	},
	GET_META_RANGE({ key }) {
		const params = new URLSearchParams({ facet: key });

		return apiFetch({
			path: `/elasticpress/v1/meta-range?${params}`,
		});
	},
};

const resolvers = {
	*getMetaKeys() {
		const metaKeys = yield actions.getMetaKeys();

		return actions.setMetaKeys(metaKeys);
	},
	*getTaxonomies() {
		const taxonomies = yield actions.getTaxonomies();

		return actions.setTaxonomies(taxonomies);
	},
	*getMetaRange(key) {
		const { data: { min = false, max = false } = {} } = yield actions.getMetaRange(key);

		return actions.setMetaRange(key, { min, max });
	},
};

const store = {
	reducer,
	controls,
	selectors,
	resolvers,
	actions,
	initialState,
};

export default store;
