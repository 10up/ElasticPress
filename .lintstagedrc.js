/**
 * Plugin code must run on PHP 7.4+, the test suite on PHP 8.2+ (same as the Linting workflow).
 */
const isTestFile = (file) => /[/\\]tests[/\\]/.test(file);

const phpcs = (testVersion, files) =>
	`./vendor/bin/phpcs --extensions=php --runtime-set testVersion ${testVersion} -s ${files
		.map((file) => `"${file}"`)
		.join(' ')}`;

module.exports = {
	'*.css': ['10up-toolkit lint-style'],
	'*.js': ['10up-toolkit lint-js'],
	'*.php': (files) => {
		const pluginFiles = files.filter((file) => !isTestFile(file));
		const testFiles = files.filter(isTestFile);

		return [
			...(pluginFiles.length ? [phpcs('7.4-', pluginFiles)] : []),
			...(testFiles.length ? [phpcs('8.2-', testFiles)] : []),
		];
	},
};
