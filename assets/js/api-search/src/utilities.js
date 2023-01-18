/**
 * Sanitize an argument value based on its type.
 *
 * @param {*} value The value.
 * @param {object} options Sanitization options.
 * @param {'number'|'numbers'|'string'|'strings'} options.type (optional) Value type.
 * @param {Array} options.allowedValues (optional) Allowed values.
 * @param {*} options.default (optional) Default value.
 * @param {boolean} [useDefaults] Whether to return default values.
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
 * @param {*} value The value.
 * @param {object} options Sanitization options.
 * @param {'number'|'numbers'|'string'|'strings'} options.type (optional) Value type.
 * @param {Array} options.allowedValues (optional) Allowed values.
 * @param {*} options.default (optional) Default value.
 * @param {boolean} [useDefaults] Whether to return default values.
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

/**
 * Get permalink URL parameters from args.
 *
 * @typedef {object} ArgSchema
 * @property {string} type Arg type.
 * @property {any} [default] Default arg value.
 * @property {Array} [allowedValues] Array of allowed values.
 *
 * @param {object} args Args
 * @param {ArgSchema} schema Args schema.
 * @param {string} [prefix] Prefix to prepend to args.
 * @returns {URLSearchParams} URLSearchParams instance.
 */
export const getUrlParamsFromArgs = (args, schema, prefix = '') => {
	const urlParams = new URLSearchParams();

	Object.entries(schema).forEach(([arg, options]) => {
		const param = prefix + arg;
		const value = typeof args[arg] !== 'undefined' ? sanitizeParam(args[arg], options) : null;

		if (value !== null) {
			urlParams.set(param, value);
		}
	});

	return urlParams;
};

/**
 * Build request args from URL parameters using a given schema.
 *
 * @typedef {object} ArgSchema
 * @property {string} type Arg type.
 * @property {any} [default] Default arg value.
 * @property {Array} [allowedValues] Array of allowed values.
 *
 * @param {Object<string, ArgSchema>} argsSchema Schema to build args from.
 * @param {string} [paramPrefix] Parameter prefix.
 * @returns {Object<string, any>} Query args.
 */
export const getArgsFromUrlParams = (argsSchema, paramPrefix = '') => {
	const urlParams = new URLSearchParams(window.location.search);

	const args = Object.entries(argsSchema).reduce((args, [arg, options]) => {
		const param = urlParams.get(paramPrefix + arg);
		const value = typeof param !== 'undefined' ? sanitizeArg(param, options, false) : null;

		if (value !== null) {
			args[arg] = value;
		}

		return args;
	}, {});

	return args;
};

/**
 * Build request args from defaults provided in a given schema.
 *
 * @param {Object<string, ArgSchema>} argsSchema Schema to build args from.
 * @returns {Object<string, any>} Query args.
 */
export const getDefaultArgsFromSchema = (argsSchema) => {
	return Object.entries(argsSchema).reduce((args, [arg, schema]) => {
		const hasDefault = Object.hasOwnProperty.call(schema, 'default');

		if (hasDefault) {
			args[arg] = schema.default;
		}

		return args;
	}, {});
};

/**
 * Get the current URL, including parameters, with any prefixed parameters
 * replaced with the given parameters.
 *
 * @param {string} paramPrefix Prefix of parameters to replace.
 * @param {object} params Parameters to add, if any.
 * @returns {string} URL.
 */
export const getUrlWithParams = (paramPrefix, params) => {
	const url = new URL(window.location.href);
	const keys = Array.from(url.searchParams.keys());

	for (const key of keys) {
		if (key.startsWith(paramPrefix)) {
			url.searchParams.delete(key);
		}
	}

	if (params) {
		params.forEach((value, key) => {
			url.searchParams.set(key, value);
		});
	}

	return url.toString();
};

/**
 * Clear facet filters from a set of args.
 *
 * @param {object} args Args to clear facets from.
 * @param {object} argsSchema Args schema.
 * @returns {object} Cleared args.
 */
export const getArgsWithoutConstraints = (args, argsSchema) => {
	const clearedArgs = { ...args };

	Object.entries(argsSchema).forEach(([arg, schema]) => {
		const hasDefault = Object.hasOwnProperty.call(schema, 'default');

		if (!hasDefault) {
			delete clearedArgs[arg];
		}
	});

	return clearedArgs;
};
