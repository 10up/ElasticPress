/**
 * WordPress dependencies.
 */
import { Button } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { undo } from '@wordpress/icons';

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
	return <Button className="ep-weighting-undo" icon={undo} {...props} />;
};
