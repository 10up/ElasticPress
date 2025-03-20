/**
 * WordPress dependencies.
 */
import { createRoot, render } from '@wordpress/element';

/**
 * Internal dependences.
 */
import FacetSelector from './components/facet-selector';

document.addEventListener('DOMContentLoaded', () => {
	const input = document.getElementById('feature_instant_results_facets');

	if (!input) {
		return;
	}

	const {
		className,
		dataset: { fieldName },
		id,
		name,
		value,
	} = input;

	if (typeof createRoot === 'function') {
		const root = createRoot(input.parentElement);

		root.render(
			<FacetSelector
				className={className}
				data-field-name={fieldName}
				defaultValue={value}
				id={id}
				name={name}
			/>,
		);
	} else {
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
	}
});
