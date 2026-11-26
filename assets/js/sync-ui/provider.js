/**
 * WordPress dependencies.
 */
import { useDispatch } from '@wordpress/data';
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
	WPElement,
} from '@wordpress/element';
import { store as noticeStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

import { apiUrl, nonce } from './config';

/**
 * Sync context.
 */
const Context = createContext();

/**
 * Sync settings provider.
 *
 * @param {object} props Component props.
 * @param {boolean} props.autoIndex Whether to start an index automatically.
 * @param {Function} props.children Component children
 * @param {Array} props.indexables Indexables and their labels.
 * @param {object|null} props.indexMeta Current sync metadata.
 * @param {Array|null} props.sites Eligible network sites.
 * @param {Array} props.postTypes Post types and their labels.
 * @returns {WPElement}
 */
export const SyncSettingsProvider = ({
	autoIndex,
	children,
	indexables,
	indexMeta,
	postTypes: defaultPostTypes,
	sites,
}) => {
	const { createNotice } = useDispatch(noticeStore);

	const [args, setArgs] = useState({
		include: [],
		indexables: [],
		lower_limit_object_id: '',
		offset: 0,
		put_mapping: false,
		post_type: [],
		site_id: indexMeta?.site_id ? String(indexMeta.site_id) : '',
		upper_limit_object_id: '',
		...indexMeta?.sync_args,
	});

	const [showLog, setShowLog] = useState(false);
	const [postTypes, setPostTypes] = useState(defaultPostTypes);
	const [isLoadingPostTypes, setIsLoadingPostTypes] = useState(Boolean(args.site_id));
	const [postTypesError, setPostTypesError] = useState('');

	useEffect(() => {
		if (!sites || !args.site_id) {
			setPostTypes(defaultPostTypes);
			setIsLoadingPostTypes(false);
			setPostTypesError('');
			return undefined;
		}

		const controller = new AbortController();
		const url = new URL(apiUrl);
		const route = url.searchParams.get('rest_route');

		if (route) {
			url.searchParams.set('rest_route', `${route}/post-types`);
		} else {
			url.pathname += '/post-types';
		}

		url.searchParams.set('site_id', args.site_id);
		setIsLoadingPostTypes(true);
		setPostTypesError('');
		setPostTypes([]);
		fetch(url, {
			headers: { 'X-WP-Nonce': nonce },
			signal: controller.signal,
		})
			.then(async (response) => {
				const data = await response.json();

				if (!response.ok) {
					throw new Error(data.message);
				}

				setPostTypes(data);
				setIsLoadingPostTypes(false);
			})
			.catch((error) => {
				if (error.name !== 'AbortError') {
					setPostTypesError(
						error.message ||
							__('Unable to load the selected site’s post types.', 'elasticpress'),
					);
					setIsLoadingPostTypes(false);
				}
			});

		return () => controller.abort();
	}, [args.site_id, defaultPostTypes, sites]);

	const selectSite = useCallback(
		(siteId) => {
			setIsLoadingPostTypes(Boolean(siteId));
			setArgs({
				...args,
				site_id: siteId,
				post_type: [],
				include: [],
				lower_limit_object_id: '',
				upper_limit_object_id: '',
				offset: 0,
			});
		},
		[args],
	);
	const hasSelectedSiteContent = indexables.some(
		([slug, , isGlobal]) => !isGlobal && args.indexables.includes(slug),
	);
	const hasOnlyGlobalIndexables =
		Boolean(args.site_id) && args.indexables.length > 0 && !hasSelectedSiteContent;

	const value = useMemo(
		() => ({
			args,
			autoIndex,
			createNotice,
			indexables,
			hasOnlyGlobalIndexables,
			postTypes,
			isLoadingPostTypes,
			postTypesError,
			selectSite,
			sites,
			setArgs,
			showLog,
			setShowLog,
		}),
		[
			args,
			autoIndex,
			createNotice,
			indexables,
			hasOnlyGlobalIndexables,
			postTypes,
			isLoadingPostTypes,
			postTypesError,
			selectSite,
			sites,
			setArgs,
			showLog,
			setShowLog,
		],
	);

	return <Context.Provider value={value}>{children}</Context.Provider>;
};

/**
 * Use the API Search context.
 *
 * @returns {object} API Search Context.
 */
export const useSyncSettings = () => {
	return useContext(Context);
};
