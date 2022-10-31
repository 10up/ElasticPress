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
 * @param {Function} props.onReset Reset handler.
 * @returns {WPElement} Component element.
 */
export default ({ isBusy, isChanged, onReset }) => {
	/**
	 * Render.
	 */
	return (
		<>
			<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit">
				{__('Save Changes', 'elasticpress')}
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
