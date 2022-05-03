/**
 * WordPress dependencies.
 */
import { PanelBody } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Log from './common/log';

/**
 * Sync button component.
 *
 * @param {object} props Component props.
 * @param {object[]} props.messages Log messages.
 * @returns {WPElement} Component.
 */
export default ({ messages }) => {
	return (
		<PanelBody title={__('Log', 'elasticpress')} initialOpen={false}>
			<Log messages={messages} />
		</PanelBody>
	);
};
