/**
 * Get a list of meta fields from the weighting configuration.
 *
 * @param {object} weightingConfiguration Weighting configuration.
 * @returns {Array} Meta fields.
 */
export const getMetaFieldsFromWeightingConfiguration = (weightingConfiguration) => {
	const fields = Object.values(weightingConfiguration)
		.map((v) => Object.keys(v))
		.flat();

	return fields.filter((f) => f.startsWith('meta.'));
};

/**
 * Is a field synced by a feature?
 *
 * @param {string} field Field name.
 * @param {object} weightableFields Weightable fields.
 * @returns {boolean} If the field is synced by a feature.
 */
export const isFieldSyncedByFeature = (field, weightableFields) => {
	return Object.values(weightableFields)
		.map((v) => Object.values(v.groups))
		.flat()
		.map((g) => Object.values(g.children))
		.flat()
		.some((c) => c.key === field && c.used_by_feature === true);
};
