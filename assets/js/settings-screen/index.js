/**
 * WordPress dependencies.
 */
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createContext, useContext, useMemo, WPElement } from '@wordpress/element';
import { store as noticeStore } from '@wordpress/notices';

/**
 * Internal dependencies.
 */
import './style.css';

const Context = createContext();

/**
 * ElasticPress Settings Screen provider component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {string} props.title Page title.
 * @returns {WPElement} Sync page component.
 */
export const SettingsScreenProvider = ({ children, title }) => {
	const { createNotice, removeNotice } = useDispatch(noticeStore);

	const { notices } = useSelect((select) => {
		return {
			notices: select(noticeStore).getNotices(),
		};
	}, []);

	const contextValue = useMemo(
		() => ({
			createNotice,
			removeNotice,
		}),
		[createNotice, removeNotice],
	);

	return (
		<Context.Provider value={contextValue}>
			<div className="ep-settings-page">
				<div className="ep-settings-page__wrap">
					<h1 className="ep-settings-page__title">{title}</h1>
					{children}
				</div>
				<SnackbarList
					className="ep-settings-page__snackbar-list"
					notices={notices}
					onRemove={(notice) => removeNotice(notice)}
				/>
			</div>
		</Context.Provider>
	);
};

/**
 * Use the Settings Page.
 *
 * @returns {object} Settings Page Context.
 */
export const useSettingsScreen = () => {
	return useContext(Context);
};
