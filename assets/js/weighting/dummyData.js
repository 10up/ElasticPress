export const dummyData = [
	{
		label: 'Posts',
		name: 'post',
		indexable: true,
		order: 0,

		attributes: [
			{
				label: 'Title',
				name: 'post_title',
				indexable: true,
				searchable: true,
				weight: 40,
			},
			{
				label: 'Content',
				name: 'post_content',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Excerpt',
				name: 'post_excerpt',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Author',
				name: 'post_author',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],

		taxonomies: [
			{
				label: 'Categories',
				name: 'post_categories',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Tags',
				name: 'post_tags',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Formats',
				name: 'post_formats',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],

		meta: [
			// {
			// 	name: 'example_key',
			// 	searchable: false,
			// 	weight: 10,
			// },
			// {
			// 	name: 'another_key',
			// 	searchable: false,
			// 	weight: 10,
			// },
			// {
			// 	name: 'one_more_key',
			// 	searchable: false,
			// 	weight: 10,
			// },
		],
	},
	{
		label: 'Pages',
		name: 'page',
		indexable: true,
		order: 1,

		attributes: [
			{
				label: 'Title',
				name: 'post_title',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Content',
				name: 'post_content',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Excerpt',
				name: 'post_excerpt',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Author',
				name: 'post_author',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],

		taxonomies: [
			{
				label: 'Categories',
				name: 'post_categories',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Tags',
				name: 'post_tags',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Formats',
				name: 'post_formats',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],

		meta: [],
	},
	{
		label: 'Product',
		name: 'product',
		indexable: true,
		order: 2,

		attributes: [
			{
				label: 'Title',
				name: 'post_title',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Content',
				name: 'post_content',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Excerpt',
				name: 'post_excerpt',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Author',
				name: 'post_author',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],

		taxonomies: [
			{
				label: 'Categories',
				name: 'post_categories',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Tags',
				name: 'post_tags',
				indexable: false,
				searchable: false,
				weight: 1,
			},
			{
				label: 'Formats',
				name: 'post_formats',
				indexable: false,
				searchable: false,
				weight: 1,
			},
		],
		meta: [],
	},
];

export const dummyMetaKeys = [
	{
		name: 'example_key',
		searchable: false,
		weight: 10,
	},
	{
		name: 'another_key',
		searchable: false,
		weight: 10,
	},
	{
		name: 'one_more_key',
		searchable: false,
		weight: 10,
	},
];
