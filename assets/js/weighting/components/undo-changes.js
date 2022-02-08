/**
 * Wordpress Dependencies.
 */
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';

const UndoChanges = ({ undoHandler, undoProps }) => {
	return (
		<button type="button" className="undo-changes" onClick={() => undoHandler(undoProps)}>
			{__('Undo changes', 'elasticpress')}
		</button>
	);
};

UndoChanges.propTypes = {
	undoHandler: PropTypes.func.isRequired,
	undoProps: PropTypes.object.isRequired,
};

export default UndoChanges;
