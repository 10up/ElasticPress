/**
 * WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { AlignmentToolbar, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Placeholder, Spinner, QueryControls } from '@wordpress/components';
import { Fragment, RawHTML, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Related Posts block edit component.
 *
 * @param {object} props Component props.
 * @param {object} props.attributes Block attributes.
 * @param {string} props.className Additional CSS class(es).
 * @param {object} props.context Block context,
 * @param {Function} props.setAttributes Attribute setter.
 * @returns {Function} Component element.
 */
const RelatedPostsEdit = ({ attributes, className, context, setAttributes }) => {
	const { alignment, number } = attributes;
	const [posts, setPosts] = useState(false);

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
			path: addQueryArgs(`/wp/v2/posts/${postId}/related`, urlArgs),
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
				<PanelBody title={__('Related Post Settings', 'elasticpress')}>
					<QueryControls
						numberOfItems={number}
						onNumberOfItemsChange={(value) => setAttributes({ number: value })}
					/>
				</PanelBody>
			</InspectorControls>

			<div className={className}>
				{displayPosts === false || displayPosts.length === 0 ? (
					<Placeholder icon="admin-post" label={__('Related Posts', 'elasticpress')}>
						{posts === false ? (
							<Spinner />
						) : (
							__('No related posts yet.', 'elasticpress')
						)}
					</Placeholder>
				) : (
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
				)}
			</div>
		</Fragment>
	);
};

export default RelatedPostsEdit;
