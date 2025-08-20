const epEslintrc = require('../../.eslintrc.js');

module.exports = {
	...epEslintrc,
	rules: {
		...epEslintrc.rules,
		'import/no-unresolved': 'off',
	},
};
