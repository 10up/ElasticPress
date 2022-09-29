const defaultEslintrc = require('10up-toolkit/config/.eslintrc');

module.exports = {
	...defaultEslintrc,
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
