/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useSync } from '../../sync';
import { useSyncSettings } from '../provider';

/**
 * Network site selection.
 *
 * @returns {WPElement|null} Site selector.
 */
export default () => {
	const sync = useSync();
	const { args, setArgs, sites } = useSyncSettings();

	const options = [
		{ label: __('All eligible sites', 'elasticpress'), value: '' },
		...(sites || []),
	];
	const isUnavailable = args.site_id && !sites?.some((site) => site.value === args.site_id);

	if (isUnavailable) {
		options.push({
			label: sprintf(
				/* translators: %s: Site ID. */
				__('Site %s (unavailable)', 'elasticpress'),
				args.site_id,
			),
			value: args.site_id,
		});
	}

	return sites ? (
		<SelectControl
			label={__('Sites to sync', 'elasticpress')}
			help={__(
				'Shared network content is synced only when All eligible sites is selected.',
				'elasticpress',
			)}
			value={args.site_id}
			options={options}
			disabled={sync.isSyncing}
			onChange={(siteId) => setArgs({ ...args, site_id: siteId })}
			__nextHasNoMarginBottom
		/>
	) : null;
};
