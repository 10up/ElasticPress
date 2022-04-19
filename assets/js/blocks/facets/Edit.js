const { __ } = wp.i18n;

const { AlignmentToolbar, BlockControls, InspectorControls } = wp.editor;

const { PanelBody, QueryControls } = wp.components;

const { Fragment, Component } = wp.element;

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

		this.state = {};
	}

	/**
	 * Load preview data
	 */
	componentDidMount() {}

	render() {
		const {
			attributes: { alignment, number },
			setAttributes,
			className,
		} = this.props;

		return (
			<Fragment>
				<BlockControls>
					<AlignmentToolbar
						value={alignment}
						onChange={(newValue) => setAttributes({ alignment: newValue })}
					/>
				</BlockControls>
				<InspectorControls>
					<PanelBody title={__('Related Post Settings')}>
						<QueryControls
							numberOfItems={number}
							onNumberOfItemsChange={(value) => setAttributes({ number: value })}
						/>
					</PanelBody>
				</InspectorControls>

				<div className={className}>
					<p>test</p>
				</div>
			</Fragment>
		);
	}
}

export default Edit;
