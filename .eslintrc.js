const defaultEslintrc = require('10up-toolkit/config/.eslintrc');

module.exports = {
	...defaultEslintrc,
	'jsdoc/check-tag-names': [
		'error',
		{
			definedTags: ['filter', 'action'],
		},
	],
};
