/**
 * WordPress dependencies.
 */
import { FormTokenField } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const { isSyncing } = useSync();
	const { args, setArgs } = useSyncSettings();

	/**
	 * Handle change to method for indexing objects.
	 *
	 * @param {string} include Selected IDs.
	 * @returns {void}
	 */
	const onChange = (include) => {
		setArgs({ ...args, include });
	};

	/**
	 * Santitize IDs entered into the include field.
	 *
	 * The FormTokenField component requires string values, so return an empty
	 * string for non-numerical values.
	 *
	 * @param {string} value Value to transform.
	 * @returns {string} Transformed values.
	 */
	const saveTransform = (value) => {
		const transformedValue = parseInt(value, 10);

		return transformedValue ? transformedValue.toString() : '';
	};

	return (
		<div className="ep-sync-advanced-control">
			<FormTokenField
				disabled={isSyncing}
				label={__('Object IDs', 'elasticpress')}
				onChange={onChange}
				saveTransform={saveTransform}
				value={args.include}
			/>
		</div>
	);
};
