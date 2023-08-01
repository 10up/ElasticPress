/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependences.
 */
import FacetSelector from './components/facet-selector';

document.addEventListener('DOMContentLoaded', () => {
	const input = document.getElementById('feature_instant_results_facets');
	const root = createRoot(input.parentElement);

	const {
		className,
		dataset: { fieldName },
		id,
		name,
		value,
	} = input;

	root.render(
		<FacetSelector
			className={className}
			data-field-name={fieldName}
			defaultValue={value}
			id={id}
			name={name}
		/>,
	);
});
