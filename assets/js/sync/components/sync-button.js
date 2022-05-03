import { Button } from '@wordpress/components';
import { useContext, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { external, update } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { SyncContext } from '../context';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isDelete Whether button is a delete button.
 * @param {Function} props.onClick Click callback.
 * @returns {WPElement} Component.
 */
export default ({ isDelete, onClick }) => {
	const { isSyncing } = useContext(SyncContext);

	/**
	 * Render.
	 */
	return (
		<>
			<Button
				disabled={isSyncing}
				icon={isDelete ? null : update}
				isDestructive={isDelete}
				onClick={onClick}
				variant={isDelete ? 'secondary' : 'primary'}
			>
				{isDelete
					? __('Delete all Data and Start a Fresh Sync', 'elasticpress')
					: __('Sync now', 'elasticpress')}
			</Button>
			{!isDelete && (
				<Button
					icon={external}
					variant="link"
					href="https://elasticpress.zendesk.com/hc/en-us/articles/5205632443533"
				>
					{__('Learn more', 'elasticpress')}
				</Button>
			)}
		</>
	);
};
