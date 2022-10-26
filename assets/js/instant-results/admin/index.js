/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependences.
 */
import FacetSelector from './components/facet-selector';

document.addEventListener('DOMContentLoaded', () => {
	const input = document.getElementById('feature_instant_results_facets');

	const {
		className,
		dataset: { fieldName },
		id,
		name,
		value,
	} = input;

	render(
		<FacetSelector
			className={className}
			data-field-name={fieldName}
			defaultValue={value}
			id={id}
			name={name}
		/>,
		input.parentElement,
	);
});
