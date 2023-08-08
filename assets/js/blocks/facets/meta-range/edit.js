/* eslint-disable no-nested-ternary */
/**
 * WordPress dependencies.
 */
import { getBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps, Warning } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Flex,
	FlexItem,
	Placeholder,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import icon from './icon';
import LoadingResponsePlaceholder from '../common/components/loading-response-placeholder';
import FacetMetaControl from '../common/components/facet-meta-control';
import RangeFilter from '../common/components/range-filter';

/**
 * Facet by Meta Range block edit component.
 *
 * @param {object} props Components props.
 * @param {object} props.attributes Block attributes.
 * @param {string} props.name Block name.
 * @param {Function} props.setAttributes Block attribute setter.
 * @returns {WPElement} Component element.
 */
export default ({ attributes, name, setAttributes }) => {
	const { facet, prefix, suffix } = attributes;

	const { title } = getBlockType(name);

	const blockProps = useBlockProps();

	const {
		min = false,
		max = false,
		isLoading = false,
	} = useSelect(
		(select) => {
			const range = select('elasticpress').getMetaRange(facet) || { isLoading: true };

			return range;
		},
		[facet],
	);

	/**
	 * Facet change handler.
	 *
	 * @param {string} facet Selected facet.
	 * @returns {void}
	 */
	const onChangeFacet = (facet) => {
		setAttributes({ facet });
	};

	/**
	 * Prefix change handler.
	 *
	 * @param {string} prefix Prefix value.
	 * @returns {void}
	 */
	const onChangePrefix = (prefix) => {
		setAttributes({ prefix });
	};

	/**
	 * Suffix change handler.
	 *
	 * @param {string} suffix Suffix value.
	 * @returns {void}
	 */
	const onChangeSuffix = (suffix) => {
		setAttributes({ suffix });
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Settings', 'elasticpress')}>
					<FacetMetaControl onChange={onChangeFacet} value={facet} />
					<Flex>
						<FlexItem>
							<TextControl
								label={__('Value prefix', 'elasticpress')}
								onChange={onChangePrefix}
								value={prefix}
							/>
						</FlexItem>
						<FlexItem>
							<TextControl
								label={__('Value suffix', 'elasticpress')}
								onChange={onChangeSuffix}
								value={suffix}
							/>
						</FlexItem>
					</Flex>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{facet ? (
					isLoading ? (
						<LoadingResponsePlaceholder />
					) : min !== false && max !== false ? (
						<Disabled>
							<RangeFilter
								min={min}
								max={max}
								prefix={prefix}
								suffix={suffix}
								value={[min, max]}
							/>
						</Disabled>
					) : (
						<Warning>
							{sprintf(
								/* translators: %s: Field name. */
								__(
									'Preview unavailable. The "%s" field does not appear to contain numeric values. Select a new meta field key or populate the field with numeric values to enable filtering by range.',
									'elasticpress',
								),
								facet,
							)}
						</Warning>
					)
				) : (
					<Placeholder icon={icon} label={title}>
						<FacetMetaControl onChange={onChangeFacet} value={facet} />
					</Placeholder>
				)}
			</div>
		</>
	);
};
