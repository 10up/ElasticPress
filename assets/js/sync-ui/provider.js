/**
 * WordPress dependencies.
 */
import { useDispatch } from '@wordpress/data';
import { createContext, useContext, useMemo, useState, WPElement } from '@wordpress/element';
import { store as noticeStore } from '@wordpress/notices';

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
 * @param {Array} props.postTypes Post types and their labels.
 * @returns {WPElement}
 */
export const SyncSettingsProvider = ({ autoIndex, children, indexables, postTypes }) => {
	const { createNotice } = useDispatch(noticeStore);

	const [args, setArgs] = useState({
		include: [],
		indexables: [],
		lower_limit_object_id: null,
		offset: 0,
		put_mapping: false,
		post_type: [],
		upper_limit_object_id: null,
	});

	const [showLog, setShowLog] = useState(false);

	const value = useMemo(
		() => ({
			args,
			autoIndex,
			createNotice,
			indexables,
			postTypes,
			setArgs,
			showLog,
			setShowLog,
		}),
		[args, autoIndex, createNotice, indexables, postTypes, setArgs, showLog, setShowLog],
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
