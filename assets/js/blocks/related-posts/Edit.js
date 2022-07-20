/**
 * WordPress dependencies.
 */
import {
	AlignmentToolbar,
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, Placeholder, Spinner, QueryControls } from '@wordpress/components';
import { RawHTML, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Edit component.
 *
 * @param {object} props Component props.
 * @returns {Function} Edit component.
 */
const Edit = (props) => {
	const { attributes, setAttributes, context } = props;
	const { alignment, number } = attributes;

	const blockProps = useBlockProps();
	const [posts, setPosts] = useState(false);

	/**
	 * Load preview data
	 */
	useEffect(() => {
		const urlArgs = {
			number: 100,
		};

		// Use 0 if in the Widgets Screen
		const { postId = 0 } = context;

		wp.apiFetch({
			path: addQueryArgs(`/wp/v2/posts/${postId}/related`, urlArgs),
		})
			.then((posts) => {
				setPosts(posts);
			})
			.catch(() => {
				setPosts(false);
			});
	}, [context]);

	const displayPosts = posts.length > number ? posts.slice(0, number) : posts;

	return (
		<>
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

			<div {...blockProps}>
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
									<a href={post.link}>
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
		</>
	);
};

export default Edit;
