/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { undo } from '@wordpress/icons';
import { useWeighting } from '../provider';

/**
 * Actions component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { isBusy, isChanged, reset } = useWeighting();

	/**
	 * Render.
	 */
	return (
		<>
			<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit" variant="primary">
				{__('Save Changes', 'elasticpress')}
			</Button>
			&nbsp;
			<Button
				icon={undo}
				isTertiary
				onClick={reset}
				disabled={!isChanged || isBusy}
				type="reset"
			>
				{__('Undo All Changes', 'elasticpress')}
			</Button>
		</>
	);
};
