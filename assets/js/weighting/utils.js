/**
 * Sort list by order.
 *
 * @param {Array} list items to bo sorted.
 * @return {Array} sorted list by it's order.
 */
export const sortListByOrder = (list = []) => list.sort((a, b) => a.order - b.order);
