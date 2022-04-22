const { __ } = wp.i18n;

const { InspectorControls } = wp.editor;

const { PanelBody, RadioControl, SelectControl } = wp.components;

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
			attributes: { facet, orderby, order },
			setAttributes,
			className,
		} = this.props;

		return (
			<Fragment>
				<InspectorControls>
					<PanelBody title={__('Facet Settings', 'elasticpress')}>
						<SelectControl
							label={__('Taxonomy', 'elasticpress')}
							value={facet}
							options={[
								{ label: 'Big', value: '100%' },
								{ label: 'Medium', value: '50%' },
								{ label: 'Small', value: '25%' },
							]}
							onChange={(value) => setAttributes({ facet: value })}
						/>
						<RadioControl
							label={__('Order By', 'elasticpress')}
							help={__('The field used to order available options', 'elasticpress')}
							selected={orderby}
							options={[
								{ label: __('Count', 'elasticpress'), value: 'count' },
								{ label: __('Name', 'elasticpress'), value: 'name' },
							]}
							onChange={(value) => setAttributes({ orderby: value })}
						/>
						<RadioControl
							label={__('Order', 'elasticpress')}
							selected={order}
							options={[
								{ label: __('ASC', 'elasticpress'), value: 'asc' },
								{ label: __('DESC', 'elasticpress'), value: 'desc' },
							]}
							onChange={(value) => setAttributes({ order: value })}
						/>
					</PanelBody>
				</InspectorControls>

				<div className={className}>
					<p>Preview not available</p>
				</div>
			</Fragment>
		);
	}
}

export default Edit;
