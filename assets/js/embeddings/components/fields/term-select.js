import { useState, useEffect } from '@wordpress/element';
import { FormTokenField } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';

export default ({ value, onChange, disabled, taxonomy, label, placeholder = '' }) => {
	const [terms, setTerms] = useState([]);
	const fetchedTerms = useSelect((select) =>
		select(coreStore).getEntityRecords('taxonomy', taxonomy, { per_page: -1 }),
	);

	useEffect(() => {
		if (fetchedTerms) {
			setTerms(fetchedTerms);
		}
	}, [fetchedTerms]);

	// Convert term IDs to term names for display
	const displayedTokens = value
		.map((termId) => terms.find((term) => term.id === termId)?.name)
		.filter(Boolean);

	// Handle selection updates
	const handleChange = (selectedNames) => {
		const selectedTerms = terms.filter((term) => selectedNames.includes(term.name));
		onChange(selectedTerms.map((term) => term.id));
	};

	return (
		<FormTokenField
			disabled={disabled}
			label={label}
			value={displayedTokens}
			suggestions={terms.map((term) => term.name)}
			onChange={handleChange}
			placeholder={placeholder || __('Type to search for terms', 'elasticpress')}
			__experimentalShowHowTo={false}
			__nextHasNoMarginBottom
			__nextHasNoMarginTop
			__next40pxDefaultSize
		/>
	);
};
