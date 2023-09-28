/**
 * WordPress dependencies.
 */
import { Flex, FlexItem, TextControl } from '@wordpress/components';
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
	 * Handle changing the lower limit.
	 *
	 * @param {string} lower_limit_object_id Selected lower ID.
	 * @returns {void}
	 */
	const onChangeLower = (lower_limit_object_id) => {
		setArgs({ ...args, lower_limit_object_id });
	};

	/**
	 * Handle changing the upper limit.
	 *
	 * @param {string} upper_limit_object_id Selected upper ID.
	 * @returns {void}
	 */
	const onChangeUpper = (upper_limit_object_id) => {
		setArgs({ ...args, upper_limit_object_id });
	};

	return (
		<Flex className="ep-sync-advanced-control" justify="start">
			<FlexItem grow="2">
				<TextControl
					disabled={isSyncing}
					help={__('Sync objects with an ID of this number or higher.', 'elasticpress')}
					label={__('Lower object ID', 'elasticpress')}
					max={args.upper_limit_object_id}
					min="0"
					onChange={onChangeLower}
					type="number"
					value={args.lower_limit_object_id}
				/>
			</FlexItem>
			<FlexItem grow="2">
				<TextControl
					disabled={isSyncing}
					help={__('Sync objects with an ID of this number or lower.', 'elasticpress')}
					label={__('Higher object ID', 'elasticpress')}
					min={args.lower_limit_object_id}
					onChange={onChangeUpper}
					type="number"
					value={args.upper_limit_object_id}
				/>
			</FlexItem>
		</Flex>
	);
};
