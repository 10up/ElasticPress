/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	AlignmentToolbar,
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { Disabled, PanelBody, Placeholder, Spinner, QueryControls } from '@wordpress/components';
import { Fragment, RawHTML, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies.
 */
import icon from './icon';

/**
 * Related Posts block edit component.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @param {object} props.context Block context,
 * @param {Function} props.setAttributes Attribute setter.
 * @returns {Function} Component element.
 */
const RelatedPostsEdit = ({ attributes, context, setAttributes }) => {
	const { alignment, number } = attributes;
	const [posts, setPosts] = useState(false);

	const blockProps = useBlockProps();

	/**
	 * Related posts, limited by the selected number.
	 */
	const displayPosts = posts.length > number ? posts.slice(0, number) : posts;

	/**
	 * Initialize block.
	 */
	const handleInit = () => {
		const urlArgs = {
			number: 100,
		};

		const { postId = 0 } = context;

		apiFetch({
			path: addQueryArgs(`/elasticpress/v1/related-posts/${postId}`, urlArgs),
		})
			.then((posts) => {
				setPosts(posts);
			})
			.catch(() => {
				setPosts(false);
			});
	};

	/**
	 * Effects.
	 */
	useEffect(handleInit, [context]);

	return (
		<Fragment>
			<BlockControls>
				<AlignmentToolbar
					value={alignment}
					onChange={(newValue) => setAttributes({ alignment: newValue })}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={__('Settings', 'elasticpress')}>
					<QueryControls
						numberOfItems={number}
						onNumberOfItemsChange={(value) => setAttributes({ number: value })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{displayPosts === false || displayPosts.length === 0 ? (
					<Placeholder icon={icon} label={__('Related Posts', 'elasticpress')}>
						{posts === false ? (
							<Spinner />
						) : (
							__('No related posts yet.', 'elasticpress')
						)}
					</Placeholder>
				) : (
					<Disabled>
						<ul style={{ textAlign: alignment }}>
							{displayPosts.map((post) => {
								const titleTrimmed = post.title.rendered.trim();
								return (
									<li key={post.id}>
										<a href={post.link} onClick={(e) => e.preventDefault()}>
											{titleTrimmed ? (
												<RawHTML>{titleTrimmed}</RawHTML>
											) : (
												__('(Untitled)', 'elasticpress')
											)}
										</a>
									</li>
								);
							})}
						</ul>
					</Disabled>
				)}
			</div>
		</Fragment>
	);
};

export default RelatedPostsEdit;
