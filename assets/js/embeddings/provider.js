/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { createContext, WPElement, useContext, useState } from '@wordpress/element';

/**
 * Vector Embeddings context.
 */
const Context = createContext();

/**
 * Vector Embeddings app.
 *
 * @param {object} props Component props.
 * @param {string} props.apiUrl Settings Update API URL.
 * @param {Function} props.children Component children.
 * @param {object} props.postTypeConfig Post Type Configurations.
 * @param {object} props.indexablePostTypes Indexable Post Types.
 * @param {number} props.chunkSize Chunk Size.
 * @param {number} props.chunkOverlap Chunk Overlap.
 * @param {boolean} props.embeddingsFiltered Whether embeddings are filtered.
 *
 * @returns {WPElement} Element.
 */
export const VectorEmbeddingsProvider = ({
	apiUrl,
	children,
	postTypeConfig,
	indexablePostTypes,
	chunkSize,
	chunkOverlap,
	embeddingsFiltered,
}) => {
	const [isBusy, setIsBusy] = useState(false);
	const [currentSettings, setCurrentSettings] = useState({
		...{ postTypeConfig, indexablePostTypes, chunkSize, chunkOverlap, embeddingsFiltered },
	});

	const setEmbeddingForPostType = (postType, taxonomy, key, value) => {
		setCurrentSettings((prevConfig) => {
			const postTypeConfig = prevConfig.postTypeConfig.find(
				(config) => config.key === postType,
			);

			let updatedConfig;

			// the following keys are nested within the taxonomy object.
			const taxonomyKeys = ['termsInclude', 'termsExclude', 'enabled'];

			// check if we're updating a nested taxonomy value
			const updatingTaxonomy = taxonomy && taxonomyKeys.includes(key);

			if (updatingTaxonomy) {
				updatedConfig = {
					...postTypeConfig,
					taxonomies: {
						...postTypeConfig.taxonomies,
						[taxonomy]: {
							...postTypeConfig.taxonomies[taxonomy],
							[key]: value,
						},
					},
				};
			} else {
				// Update other postType-level properties
				updatedConfig = {
					...postTypeConfig,
					[key]: value,
				};
			}

			return {
				...prevConfig,
				postTypeConfig: prevConfig.postTypeConfig.map((item) =>
					item.key === postType ? { ...item, ...updatedConfig } : item,
				),
			};
		});
	};

	const setChunkSize = (value) => {
		setCurrentSettings((prevConfig) => ({
			...prevConfig,
			chunkSize: value,
		}));
	};

	const setChunkOverlap = (value) => {
		setCurrentSettings((prevConfig) => ({
			...prevConfig,
			chunkOverlap: value,
		}));
	};

	/**
	 * Save settings.
	 *
	 * @returns {void}
	 */
	const save = async () => {
		setIsBusy(true);

		try {
			await apiFetch({
				body: JSON.stringify(currentSettings),
				headers: {
					'Content-Type': 'application/json',
				},
				method: 'POST',
				url: apiUrl,
			});
		} catch (e) {
			console.error(e); // eslint-disable-line no-console
			throw e;
		} finally {
			setIsBusy(false);
		}
	};

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		currentSettings,
		isBusy,
		save,
		setEmbeddingForPostType,
		setChunkOverlap,
		setChunkSize,
	};

	/**
	 * Render.
	 */
	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useVectorEmbeddingSettings = () => {
	return useContext(Context);
};
