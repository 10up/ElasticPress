/* eslint-disable camelcase */

const {
	i18n: { __ },
	data: { useSelect, useDispatch },
	plugins: { registerPlugin },
	element: { useState, useEffect },
	components: { CheckboxControl },
	editPost: { PluginPostStatusInfo },
} = wp;

const ExcludeFromSearch = () => {
	const {
		meta,
		meta: { ep_exclude_from_search = false },
	} = useSelect((select) => ({
		meta: select('core/editor').getEditedPostAttribute('meta') || {},
	}));
	const { editPost } = useDispatch('core/editor');
	const [excludeFromSearch, setExcludeFromSearch] = useState(ep_exclude_from_search);
	useEffect(() => {
		editPost({
			meta: {
				...meta,
				ep_exclude_from_search: excludeFromSearch,
			},
		});
	}, [excludeFromSearch]);
	return (
		<PluginPostStatusInfo>
			<CheckboxControl
				label={__('Exclude from search')}
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

/* eslint-enable camelcase */
