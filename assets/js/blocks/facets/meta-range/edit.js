/* eslint-disable no-nested-ternary */
/* global facetMetaBlock */

/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps, Warning } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Placeholder,
	Spinner,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import {
	createInterpolateElement,
	useEffect,
	useMemo,
	useState,
	WPElement,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RangeFacet from './components/range-facet';

/**
 * Preview loading component.
 *
 * @returns {WPElement} Component element.
 */
const PreviewLoading = () => {
	return (
		<Placeholder>
			<Spinner />
		</Placeholder>
	);
};

/**
 * Preview component.
 *
 * @param {object} props Component props.
 * @param {number} props.min Minumum value.
 * @param {number} props.max Maximum value.
 * @param {string} props.prefix Value prefix.
 * @param {string} props.suffix Value suffix.
 * @returns {WPElement} Component element.
 */
const Preview = ({ min, max, prefix, suffix }) => {
	return (
		<Disabled>
			<RangeFacet min={min} max={max} prefix={prefix} suffix={suffix} value={[min, max]} />
		</Disabled>
	);
};

/**
 * Preview unavailable component.
 *
 * @param {object} props Component props.
 * @param {string} props.value Selected value.
 * @returns {WPElement} Component element.
 */
const PreviewUnavailable = ({ value }) => {
	return (
		<Warning>
			{sprintf(
				/* translators: %s: Field name. */
				__(
					'Preview unavailable. The "%s" field does not appear to contain numeric values. Select a new meta field key or populate the field with numeric values to enable filtering by range.',
					'elasticpress',
				),
				value,
			)}
		</Warning>
	);
};

/**
 * Key selection component.
 *
 * @param {object} props Component props.
 * @param {Function} props.onChange Change handler.
 * @param {object[]} props.options Select options.
 * @param {string} props.value Selected facet.
 * @returns {WPElement} Component element.
 */
const FacetControl = ({ onChange, options, value }) => {
	return (
		<SelectControl
			disabled={options.length <= 1}
			help={createInterpolateElement(
				__(
					'This is the list of meta fields indexed in Elasticsearch. If your desired field does not appear in this list please try to <a>sync your content</a>',
					'elasticpress',
				),
				// eslint-disable-next-line jsx-a11y/anchor-has-content, jsx-a11y/control-has-associated-label
				{ a: <a href={facetMetaBlock.syncUrl} /> },
			)}
			label={__('Meta Field Key', 'elasticpress')}
			onChange={onChange}
			options={options}
			value={value}
		/>
	);
};

/**
 * Block wizard component.
 *
 * @param {object} props Component props.
 * @returns {WPElement} Component element.
 */
const Wizard = (props) => {
	return (
		<Placeholder label={__('Facet by Meta Range', 'elasticpress')}>
			<FacetControl {...props} />
		</Placeholder>
	);
};

/**
 * Facet by Meta Range block edit component.
 *
 * @param {object} props Components props.
 * @param {object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Block attribute setter.
 * @returns {WPElement} Component element.
 */
export default (props) => {
	const { attributes, setAttributes } = props;
	const { facet, prefix, suffix } = attributes;

	const blockProps = useBlockProps();
	const [isLoading, setIsLoading] = useState(false);
	const [min, setMin] = useState(false);
	const [max, setMax] = useState(false);
	const [metaKeys, setMetaKeys] = useState([]);

	/**
	 * Key options.
	 */
	const options = useMemo(() => {
		return [
			{
				label: __('Select key', 'elasticpress'),
				value: '',
			},
			...metaKeys.map((metaKey) => ({
				label: metaKey,
				value: metaKey,
			})),
		];
	}, [metaKeys]);

	/**
	 * Change handler.
	 *
	 * @param {string} value Selected value.
	 * @returns {void}
	 */
	const onChange = (value) => {
		setAttributes({ facet: value });
	};

	/**
	 * Change handler.
	 *
	 * @param {string} value Selected value.
	 * @returns {void}
	 */
	const onChangePrefix = (value) => {
		setAttributes({ prefix: value });
	};

	/**
	 * Change handler.
	 *
	 * @param {string} value Selected value.
	 * @returns {void}
	 */
	const onChangeSuffix = (value) => {
		setAttributes({ suffix: value });
	};

	/**
	 * Handle changes to the selected facet.
	 */
	const handleFacet = () => {
		setIsLoading(true);

		const params = new URLSearchParams({ facet });

		apiFetch({
			path: `/elasticpress/v1/facets/meta-range/block-preview?${params}`,
		})
			.then((response) => {
				if (response.success) {
					setMin(response.data.min);
					setMax(response.data.max);
				} else {
					setMin(false);
					setMax(false);
				}
			})
			.finally(() => setIsLoading(false));
	};

	/**
	 * Handle initialization.
	 */
	const handleInit = () => {
		apiFetch({
			path: '/elasticpress/v1/facets/meta-range/keys',
		}).then(setMetaKeys);
	};

	/**
	 * Effects.
	 */
	useEffect(handleFacet, [facet]);
	useEffect(handleInit, []);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Facet Settings', 'elasticpress')}>
					<FacetControl onChange={onChange} options={options} value={facet} />
					<TextControl
						label={__('Value prefix', 'elasticpress')}
						onChange={onChangePrefix}
						value={prefix}
					/>
					<TextControl
						label={__('Value suffix', 'elasticpress')}
						onChange={onChangeSuffix}
						value={suffix}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{facet ? (
					isLoading ? (
						<PreviewLoading />
					) : min !== false && max !== false ? (
						<Preview min={min} max={max} prefix={prefix} suffix={suffix} />
					) : (
						<PreviewUnavailable value={facet} />
					)
				) : (
					<Wizard onChange={onChange} options={options} value={facet} />
				)}
			</div>
		</>
	);
};
