/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { withNotices } from '@wordpress/components';
import { createContext, WPElement, useContext, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isEqual } from 'lodash';

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
 * @param {object} props.noticeOperations Notice operations from withNotices.
 * @param {WPElement} props.noticeUI Notice UI from withNotices.
 * @param {object} props.weightableFields Weightable fields, indexed by post type.
 * @param {object} props.weightingConfiguration Weighting configuration, indexed by post type.
 * @returns {WPElement} Element.
 */
const WeightingProvider = ({
	apiUrl,
	children,
	metaMode,
	noticeOperations,
	noticeUI,
	weightableFields,
	weightingConfiguration,
}) => {
	const [currentWeightingConfiguration, setCurrentWeightingConfiguration] = useState({
		...weightingConfiguration,
	});

	const [savedWeightingConfiguration, setSavedWeightingConfiguration] = useState({
		...weightingConfiguration,
	});

	const [isBusy, setIsBusy] = useState(false);

	/**
	 * Is the current data different to the saved data.
	 */
	const isChanged = useMemo(
		() => !isEqual(currentWeightingConfiguration, savedWeightingConfiguration),
		[currentWeightingConfiguration, savedWeightingConfiguration],
	);

	/**
	 * Whether to show weighting for metadata.
	 */
	const isManual = useMemo(() => metaMode === 'manual', [metaMode]);

	/**
	 * Handle data change.
	 *
	 * @param {string} postType Post type to update.
	 * @param {Array} values New valus.
	 * @returns {void}
	 */
	const setWeightingForPostType = (postType, values) => {
		setCurrentWeightingConfiguration({
			...currentWeightingConfiguration,
			[postType]: values,
		});
	};

	/**
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const reset = () => {
		setCurrentWeightingConfiguration({ ...savedWeightingConfiguration });
	};

	/**
	 * Save settings.
	 *
	 * @returns {void}
	 */
	const save = async () => {
		try {
			setIsBusy(true);

			const response = await apiFetch({
				body: JSON.stringify(currentWeightingConfiguration),
				headers: {
					'Content-Type': 'application/json',
				},
				method: 'POST',
				url: apiUrl,
			});

			setSavedWeightingConfiguration(response.data);

			noticeOperations.createNotice({
				content: __('Search fields & weighting saved.', 'elasticpress'),
				status: 'success',
			});
		} catch {
			noticeOperations.createNotice({
				content: __('Whoops! Something went wrong. Please try again.', 'elasticpress'),
				status: 'error',
			});
		} finally {
			document.body.scrollIntoView({ behavior: 'smooth' });

			setIsBusy(false);
		}
	};

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		currentWeightingConfiguration,
		isBusy,
		isChanged,
		isManual,
		noticeOperations,
		noticeUI,
		reset,
		save,
		savedWeightingConfiguration,
		setWeightingForPostType,
		weightableFields,
	};

	/**
	 * Render.
	 */
	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

export default withNotices(WeightingProvider);

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useWeighting = () => {
	return useContext(Context);
};
