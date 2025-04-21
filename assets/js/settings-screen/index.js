/**
 * WordPress dependencies.
 */
import { createSlotFill, SlotFillProvider, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createContext, useContext, useMemo, WPElement } from '@wordpress/element';
import { store as noticeStore } from '@wordpress/notices';

/**
 * Internal dependencies.
 */
import './style.css';

const Context = createContext();
const { Fill, Slot } = createSlotFill('SettingsPageAction');

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
			ActionSlot: Fill,
			createNotice,
			removeNotice,
		}),
		[createNotice, removeNotice],
	);

	return (
		<SlotFillProvider>
			<Context.Provider value={contextValue}>
				<div className="ep-settings-page">
					<div className="ep-settings-page__wrap">
						<header className="ep-settings-page__header">
							<h1 className="ep-settings-page__title">{title}</h1>
							<Slot />
						</header>
						{children}
					</div>
					<SnackbarList
						className="ep-settings-page__snackbar-list"
						notices={notices}
						onRemove={(notice) => removeNotice(notice)}
					/>
				</div>
			</Context.Provider>
		</SlotFillProvider>
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
