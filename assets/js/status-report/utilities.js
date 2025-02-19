/**
 * Load a group report data via AJAX
 *
 * @param {string} id Group ID.
 *
 *
 * @returns {Promise} Group data.
 */
export const loadGroupAjax = async (id) => {
	const { ajaxurl } = window;

	const data = new FormData();
	data.append('action', 'ep_load_groups');
	data.append('report', id);
	return fetch(ajaxurl, { method: 'POST', body: data });
};
