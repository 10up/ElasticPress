const defaultEslintrc = require('10up-toolkit/config/.eslintrc');

module.exports = {
	...defaultEslintrc,
	parser: '@typescript-eslint/parser',
	plugins: ['@typescript-eslint'],
	settings: {
		...defaultEslintrc.settings,
		'import/resolver': {
			typescript: {},
			node: {
				extensions: ['.js', '.jsx', '.ts', '.tsx'],
			},
		},
	},
	rules: {
		...defaultEslintrc.rules,
		'jsdoc/check-tag-names': [
			'error',
			{
				definedTags: ['filter', 'action'],
			},
		],
	},
};
