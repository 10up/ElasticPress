/* global wp */

const {
	__
} = wp.i18n;

const {
	AlignmentToolbar,
	BlockControls,
	InspectorControls
} = wp.editor;

const {
	PanelBody,
	Placeholder,
	Spinner,
	QueryControls
} = wp.components;

const {
	Fragment,
	Component,
	RawHTML
} = wp.element;

const { addQueryArgs } = wp.url;

/**
 * Edit component
 */
class Edit extends Component {
	/**
	 * Setup class
	 */
	constructor( props ) {
		super( props );

		const { attributes: { number } } = props;

		this.state = {
			posts: false,
			number: number
		};
	}

	/**
	 * Load preview data
	 */
	componentDidMount() {
		const urlArgs = {
			number: 100
		};

		this.fetchRequest = wp.apiFetch( {
			path: addQueryArgs( `/wp/v2/posts/${  wp.data.select( 'core/editor' ).getCurrentPostId()  }/related`, urlArgs ),
		} ).then(
			( posts ) => {
				this.setState( { posts: posts } );
			}
		).catch(
			() => {
				this.setState( { posts: false } );
			}
		);
	}

	/**
	 * Render block
	 */
	render() {
		const { attributes: { alignment, number }, setAttributes, className } = this.props;
		const { posts } = this.state;

		const displayPosts = posts.length > number ? posts.slice( 0, number ) : posts;

		return (
			<Fragment>
				<BlockControls>
					<AlignmentToolbar
						value={ alignment }
						onChange={ newValue => setAttributes( { alignment: newValue } ) }
					/>
				</BlockControls>
				<InspectorControls>
					<PanelBody title={ __( 'Related Post Settings' ) }>
						<QueryControls
							numberOfItems={ number }
							onNumberOfItemsChange={ ( value ) => setAttributes( { number: value } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div className={ className }>
					{ false === displayPosts || 0 === displayPosts.length ?
						<Placeholder
							icon="admin-post"
							label={ __( 'Related Posts' ) }
						>
							{ false === posts ?
								<Spinner />
								:
								__( 'No related posts yet.' )
							}
						</Placeholder>
						:
						<ul style={ { textAlign: alignment } }>
							{ displayPosts.map( ( post, i ) => {
								const titleTrimmed = post.title.rendered.trim();
								return (
									<li key={i}>
										<a href={ post.link }>
											{ titleTrimmed ? (
												<RawHTML>
													{ titleTrimmed }
												</RawHTML>
											) :
												__( '(Untitled)', 'elasticpress' )
											}
										</a>
									</li>
								);
							} ) }
						</ul>
					}
				</div>
			</Fragment>
		);
	}
}

export default Edit;
