/**
 * WordPress dependencies.
 */
import { RadioControl } from '@wordpress/components';
import { useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';
import Include from './include';
import Limits from './limits';
import Offset from './offset';

/**
 * Delete checkbox component.
 *
 * @returns {WPElement} Sync page component.
 */
export default () => {
	const [selected, setSelected] = useState('all');

	const { isSyncing } = useSync();
	const { args, setArgs } = useSyncSettings();

	/**
	 * Handle change to method for indexing objects.
	 *
	 * @param {string} value Selected method.
	 * @returns {void}
	 */
	const onChange = (value) => {
		let { include, lower_limit_object_id, offset, upper_limit_object_id } = args;

		switch (value) {
			case 'include':
				offset = 0;
				lower_limit_object_id = null;
				upper_limit_object_id = null;
				break;
			case 'limits':
				offset = 0;
				include = [];
				break;
			default:
				include = [];
				lower_limit_object_id = null;
				upper_limit_object_id = null;
				break;
		}

		setArgs({ ...args, include, lower_limit_object_id, offset, upper_limit_object_id });
		setSelected(value);
	};

	return (
		<>
			<RadioControl
				className="ep-sync-advanced-control"
				disabled={isSyncing}
				label={__('Objects to sync', 'elasticpress')}
				onChange={onChange}
				options={[
					{
						label: 'All objects',
						value: 'all',
					},
					{
						label: __('Specific IDs', 'elasticpress'),
						value: 'include',
					},
					{
						label: __('A range of IDs', 'elasticpress'),
						value: 'limits',
					},
				]}
				selected={selected}
			/>
			{selected === 'all' ? <Offset /> : null}
			{selected === 'include' ? <Include /> : null}
			{selected === 'limits' ? <Limits /> : null}
		</>
	);
};
