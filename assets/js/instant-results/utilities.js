/**
 * Sanitize an argument value based on its type.
 *
 * @param {*}                                     value                 The value.
 * @param {object}                                options               Sanitization options.
 * @param {'number'|'numbers'|'string'|'strings'} options.type          (optional) Value type.
 * @param {Array}                                 options.allowedValues (optional) Allowed values.
 * @param {*}                                     options.default       (optional) Default value.
 * @param {boolean}                               [useDefaults]         Whether to return default values.
 * @returns {*} Sanitized value.
 */
export const sanitizeArg = (value, options, useDefaults = true) => {
	let sanitizedValue = null;

	switch (value && options.type) {
		case 'number':
			sanitizedValue = parseFloat(value, 10) || null;
			break;
		case 'numbers':
			sanitizedValue = decodeURIComponent(value)
				.split(',')
				.map((v) => parseFloat(v, 10))
				.filter(Boolean);
			break;
		case 'string':
			sanitizedValue = value.toString();
			break;
		case 'strings':
			sanitizedValue = decodeURIComponent(value)
				.split(',')
				.map((v) => v.toString().trim());
			break;
		default:
			break;
	}

	/**
	 * If there is a list of allowed values, make sure the value is
	 * allowed.
	 */
	if (options.allowedValues) {
		sanitizedValue = options.allowedValues.includes(sanitizedValue) ? sanitizedValue : null;
	}

	/**
	 * Populate a default value if one is available and we still don't
	 * have a value.
	 */
	if (useDefaults && sanitizedValue === null && typeof options.default !== 'undefined') {
		sanitizedValue = options.default;
	}

	return sanitizedValue;
};

/**
 * Sanitize a parameter value based on its type.
 *
 * @param {*}                                     value                 The value.
 * @param {object}                                options               Sanitization options.
 * @param {'number'|'numbers'|'string'|'strings'} options.type          (optional) Value type.
 * @param {Array}                                 options.allowedValues (optional) Allowed values.
 * @param {*}                                     options.default       (optional) Default value.
 * @param {boolean}                               [useDefaults]         Whether to return default values.
 * @returns {*} Sanitized value.
 */
export const sanitizeParam = (value, options, useDefaults = true) => {
	let sanitizedValue = null;

	switch (value && options.type) {
		case 'number':
		case 'string':
			sanitizedValue = value;
			break;
		case 'numbers':
		case 'strings':
			sanitizedValue = value.join(',');
			break;
		default:
			break;
	}

	/**
	 * If there is a list of allowed values, make sure the value is
	 * allowed.
	 */
	if (options.allowedValues) {
		sanitizedValue = options.allowedValues.includes(sanitizedValue) ? sanitizedValue : null;
	}

	/**
	 * Populate a default value if one is available and we still don't
	 * have a value.
	 */
	if (useDefaults && sanitizedValue === null && typeof options.default !== 'undefined') {
		sanitizedValue = options.default;
	}

	return sanitizedValue;
};
