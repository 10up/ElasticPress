import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { select, dispatch } from '@wordpress/data';
import { CheckboxControl } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginPostStatusInfo } from '@wordpress/edit-post';

const ExcludeFromSearch = () => {
	const meta = select('core/editor').getEditedPostAttribute('meta') || {};
	const { ep_exclude_from_search = false } = meta;

	const [excludeFromSearch, setExcludeFromSearch] = useState(ep_exclude_from_search);

	useEffect(() => {
		const newMeta = {
			...meta,
			ep_exclude_from_search: excludeFromSearch,
		};

		dispatch('core/editor').editPost({
			meta: newMeta,
		});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [excludeFromSearch]);

	return (
		<PluginPostStatusInfo>
			<CheckboxControl
				label={__('Exclude from Search', 'elasticpress')}
				checked={excludeFromSearch}
				onChange={(toggle) => {
					setExcludeFromSearch(toggle);
				}}
			/>
		</PluginPostStatusInfo>
	);
};

registerPlugin('ep-exclude-from-search', {
	render: ExcludeFromSearch,
	icon: null,
});
