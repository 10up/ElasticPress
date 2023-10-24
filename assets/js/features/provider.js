/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
	WPElement,
} from '@wordpress/element';
import { isEqual } from 'lodash'; // eslint-disable-line import/no-extraneous-dependencies

/**
 * Sync context.
 */
const Context = createContext();

/**
 * Feature settings provider.
 *
 * Provides data and methods for interacting with ElasticPress feature
 * settings.
 *
 * @param {object} props Component props.
 * @param {string} props.apiUrl API URL.
 * @param {Function} props.children Component children
 * @param {object} props.defaultSettings Default settings values.
 * @param {string} props.epioLogoUrl ElasticPress.io logo URL.
 * @param {object} props.features Features data.
 * @param {object} props.indexMeta Index meta.
 * @param {object} props.syncedSettings Settings at last sync.
 * @returns {WPElement}
 */
export const FeatureSettingsProvider = ({
	apiUrl,
	children,
	defaultSettings,
	epioLogoUrl,
	features,
	indexMeta,
	syncedSettings,
}) => {
	const [isBusy, setIsBusy] = useState(false);
	const [isSyncing, setIsSyncing] = useState(!!indexMeta);
	const [settings, setSettings] = useState({ ...defaultSettings });
	const [savedSettings, setSavedSettings] = useState({ ...defaultSettings });

	/**
	 * Get a feature's data by its slug.
	 *
	 * @param {string} slug Feature slug.
	 * @returns {object} Feature data.
	 */
	const getFeature = useCallback(
		(slug) => {
			return features.find((f) => f.slug === slug);
		},
		[features],
	);

	/**
	 * Whether the settings have changes.
	 */
	const isModified = useMemo(() => !isEqual(settings, savedSettings), [settings, savedSettings]);

	/**
	 * Return whether a setting change will require a sync, based on the
	 * current value.
	 */
	const willSettingRequireSync = useCallback((value, previousValue, requiresSync) => {
		return requiresSync && value && value !== '0' && value !== previousValue;
	}, []);

	/**
	 * Return whether a feature requires a sync based on the current setting
	 * values.
	 *
	 * @returns {boolean}
	 */
	const willFeatureRequireSync = useCallback(
		(feature) => {
			const { slug, settingsSchema } = feature;

			return settingsSchema.some((s) => {
				/**
				 * Settings that require a sync will only require a sync if the
				 * feature will be active.
				 */
				if (settings?.[slug]?.active !== true) {
					return false;
				}

				/**
				 * A setting requires a sync if it is flagged as requiring one,
				 * but only if it has a value. A sync is not required when a
				 * feature that requires a sync is disabled, for example.
				 */
				const requiresSync = s.requires_sync;
				const value = settings?.[slug]?.[s.key];
				const previousValue = syncedSettings?.[slug]?.[s.key];

				return willSettingRequireSync(value, previousValue, requiresSync);
			});
		},
		[settings, syncedSettings, willSettingRequireSync],
	);

	/**
	 * The features that require a sync based on the current setting values.
	 */
	const featuresRequiringSync = useMemo(() => {
		return features.reduce((featuresRequiringSync, feature) => {
			const settingsRequireSync = willFeatureRequireSync(feature);

			if (settingsRequireSync) {
				featuresRequiringSync.push(feature.slug);
			}

			return featuresRequiringSync;
		}, []);
	}, [features, willFeatureRequireSync]);

	/**
	 * Whether a sync is required  based on the current setting values.
	 */
	const isSyncRequired = useMemo(() => {
		return !!featuresRequiringSync.length;
	}, [featuresRequiringSync]);

	/**
	 * Handle resetting all settings.
	 *
	 * @returns {void}
	 */
	const resetSettings = () => {
		setSettings({ ...savedSettings });
	};

	/**
	 * Save settings.
	 *
	 * @returns {void}
	 */
	const saveSettings = async () => {
		try {
			setIsBusy(true);

			await apiFetch({
				body: JSON.stringify(settings),
				headers: {
					'Content-Type': 'application/json',
				},
				method: 'PUT',
				url: apiUrl,
			});

			setSavedSettings({ ...settings });
		} finally {
			setIsBusy(false);
		}
	};

	// eslint-disable-next-line react/jsx-no-constructed-context-values
	const contextValue = {
		epioLogoUrl,
		features,
		featuresRequiringSync,
		getFeature,
		isBusy,
		isModified,
		isSyncing,
		setIsSyncing,
		isSyncRequired,
		resetSettings,
		saveSettings,
		savedSettings,
		syncedSettings,
		settings,
		setSettings,
		willSettingRequireSync,
	};

	return <Context.Provider value={contextValue}>{children}</Context.Provider>;
};

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useFeatureSettings = () => {
	return useContext(Context);
};
