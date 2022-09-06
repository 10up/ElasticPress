/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody } from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Window dependencies.
 */
const { searchablePostTypes } = window.epComments;

/**
 * Edit component.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Block attribute setter.
 * @returns {Function} Component.
 */
export default ({ attributes, setAttributes }) => {
	const { postTypes } = attributes;

	const blockProps = useBlockProps({
		className: 'ep-widget-search-comments',
	});

	const allSelected = useMemo(() => {
		return postTypes.length === 0;
	}, [postTypes]);

	/**
	 * Handle checking a post type option.
	 *
	 * @param {string} postType Post type slug.
	 * @param {boolean} checked Whether post type is selected.
	 * @returns {void}
	 */
	const onChange = (postType, checked) => {
		const newPostTypes = checked
			? [...postTypes, postType]
			: postTypes.filter((v) => v !== postType);

		setAttributes({ postTypes: newPostTypes });
	};

	/**
	 * Handle selecting all post types.
	 *
	 * @returns {void}
	 */
	const onSelectAll = () => {
		setAttributes({ postTypes: [] });
	};

	return (
		<>
			<div {...blockProps}>
				<input
					autoComplete="off"
					className="ep-widget-search-comments-input"
					disabled
					type="search"
				/>
			</div>
			<InspectorControls>
				<PanelBody title={__('Search settings', 'elasticpress')}>
					<CheckboxControl
						checked={allSelected}
						disabled={allSelected}
						label={__('Search all comments', 'elasticpress')}
						onChange={onSelectAll}
					/>
					{Object.entries(searchablePostTypes).map(([postType, postTypeLabel]) => {
						const label = sprintf(
							/* translators: %s: Post type label, plural. */
							__('Search comments on %s', 'elasticpress'),
							postTypeLabel,
						);

						return (
							<CheckboxControl
								checked={postTypes.includes(postType)}
								indeterminate={allSelected}
								label={label}
								onChange={(checked) => onChange(postType, checked)}
							/>
						);
					})}
				</PanelBody>
			</InspectorControls>
		</>
	);
};
