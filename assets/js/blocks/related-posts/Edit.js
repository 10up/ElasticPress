/**
 * WordPress dependencies.
 */
import { AlignmentToolbar, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Placeholder, Spinner, QueryControls } from '@wordpress/components';
import { Fragment, Component, RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Edit component
 */
class Edit extends Component {
	/**
	 * Setup class
	 *
	 * @param {object} props Component properties
	 */
	constructor(props) {
		super(props);

		this.state = {
			posts: false,
		};
	}

	/**
	 * Load preview data
	 */
	componentDidMount() {
		const urlArgs = {
			number: 100,
		};

		// Use 0 if in the Widgets Screen
		const { context: { postId = 0 } = {} } = this.props;

		wp.apiFetch({
			path: addQueryArgs(`/wp/v2/posts/${postId}/related`, urlArgs),
		})
			.then((posts) => {
				this.setState({ posts });
			})
			.catch(() => {
				this.setState({ posts: false });
			});
	}

	render() {
		const {
			attributes: { alignment, number },
			setAttributes,
			className,
		} = this.props;
		const { posts } = this.state;

		const displayPosts = posts.length > number ? posts.slice(0, number) : posts;

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
			</Fragment>
		);
	}
}

export default Edit;
