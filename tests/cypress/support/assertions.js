/**
 * Assert that a value should be a list of elements sorted alphabetically by
 * its text content.
 */
chai.Assertion.addMethod('elementsSortedAlphabetically', function () {
	const actual = this._obj.toArray().map(($el) => $el.textContent);
	const expected = [...actual].sort((a, b) => a.localeCompare(b));

	this.assert(
		new chai.Assertion(actual).to.deep.equal(expected),
		' Expected #{this} to be sorted alphabetically',
	);
});
