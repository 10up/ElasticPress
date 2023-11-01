/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies.
 */
import EmptyResponsePlaceholder from '../common/components/empty-response-placeholder';
import LoadingResponsePlaceholder from '../common/components/loading-response-placeholder';

const FacetDate = (props) => {
	const blockProps = useBlockProps();
	const { attributes, name, setAttributes } = props;
	const { displayCustomDate } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Settings', 'elasticpress')}>
					<ToggleControl
						label={__('Display custom date option', 'elasticpress')}
						checked={displayCustomDate}
						onChange={(displayCustomDate) => setAttributes({ displayCustomDate })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<Disabled>
					<ServerSideRender
						attributes={{
							...attributes,
							isPreview: true,
						}}
						block={name}
						EmptyResponsePlaceholder={EmptyResponsePlaceholder}
						LoadingResponsePlaceholder={LoadingResponsePlaceholder}
						skipBlockSupportAttributes
					/>
				</Disabled>
			</div>
		</>
	);
};

export default FacetDate;
