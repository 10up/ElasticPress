/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { createContext, WPElement, useContext, useMemo, useState } from '@wordpress/element';

/**
 * Instant Results context.
 */
const Context = createContext();

/**
 * Weighting settings app.
 *
 * @param {object} props Component props.
 * @param {string} props.apiUrl API URL.
 * @param {Function} props.children Component children.
 * @param {'auto'|'manual'} props.metaMode Metadata management mode.
 * @param {object} props.weightableFields Weightable fields, indexed by post type.
 * @param {object} props.weightingConfiguration Weighting configuration, indexed by post type.
 * @returns {WPElement} Element.
 */
export const WeightingSettingsProvider = ({
	apiUrl,
	children,
	metaMode,
	weightableFields,
	weightingConfiguration,
}) => {
	const [currentWeightingConfiguration, setCurrentWeightingConfiguration] = useState({
		...weightingConfiguration,
	});

	const [isBusy, setIsBusy] = useState(false);

	/**
	 * Whether to show weighting for metadata.
	 */
	const isManual = useMemo(() => metaMode === 'manual', [metaMode]);

	/**
	 * Handle data change.
	 *
	 * @param {string} postType Post type to update.
	 * @param {Array} values New values.
	 * @returns {void}
	 */
	const setWeightingForPostType = (postType, values) => {
		setCurrentWeightingConfiguration({
			...currentWeightingConfiguration,
			[postType]: values,
		});
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
				body: JSON.stringify(currentWeightingConfiguration),
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
		currentWeightingConfiguration,
		isBusy,
		isManual,
		save,
		setWeightingForPostType,
		weightableFields,
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
export const useWeightingSettings = () => {
	return useContext(Context);
};
