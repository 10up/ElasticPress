/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { trash } from '@wordpress/icons';

/**
 * Undo button component.
 *
 * @param {object} props Component props.
 * @returns {WPElement} Component element.
 */
export default (props) => {
	/**
	 * Render.
	 */
	return (
		<Button
			className="ep-weighting-action ep-weighting-action--delete"
			icon={trash}
			{...props}
		/>
	);
};
