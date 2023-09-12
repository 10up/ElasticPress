/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useWeighting } from '../provider';

/**
 * Actions component.
 *
 * @returns {WPElement} Component element.
 */
export default () => {
	const { isBusy } = useWeighting();

	/**
	 * Render.
	 */
	return (
		<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit" variant="primary">
			{__('Save Changes', 'elasticpress')}
		</Button>
	);
};
