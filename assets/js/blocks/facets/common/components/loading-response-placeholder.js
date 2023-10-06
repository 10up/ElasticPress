/**
 * WordPress dependencies.
 */
import { Placeholder, Spinner } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

/**
 * Loading preview placeholder.
 *
 * @returns {WPElement}
 */
export default () => (
	<Placeholder>
		<Spinner />
	</Placeholder>
);
