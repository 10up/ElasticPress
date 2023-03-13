/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { undo } from '@wordpress/icons';

/**
 * Actions component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isBusy Is the app busy?
 * @param {boolean} props.isChanged Are there changes?
 * @param {boolean} props.isSyncRequired Is a sync required?
 * @param {Function} props.onReset Reset handler.
 * @returns {WPElement} Component element.
 */
export default ({ isBusy, isChanged, isSyncRequired, onReset }) => {
	/**
	 * Render.
	 */
	return (
		<>
			<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit">
				{isSyncRequired ? __('Save Changes and Sync') : __('Save Changes', 'elasticpress')}
			</Button>
			&nbsp;
			<Button
				icon={undo}
				isTertiary
				onClick={onReset}
				disabled={!isChanged || isBusy}
				type="reset"
			>
				{__('Undo All Changes', 'elasticpress')}
			</Button>
		</>
	);
};
