/**
 * WordPress dependencies.
 */
import * as wpIcons from '@wordpress/icons';

/**
 * Resolve icons from the @wordpress/icons namespace.
 *
 * WordPress 6.2 ships a much older icons package than the v15 we compile
 * against. Named imports of icons that were added or renamed later (for
 * example `pencil`, formerly `edit`) are undefined, and destructuring from
 * a missing `wp.icons` global throws and leaves the React app unmounted.
 *
 * @since 5.3.4
 */
const icons = wpIcons && typeof wpIcons === 'object' ? wpIcons : {};

const { Icon, chevronDown, chevronUp, closeSmall, edit, pencil: pencilIcon, trash } = icons;

export { Icon, chevronDown, chevronUp, closeSmall, trash };

export const pencil = pencilIcon ?? edit;
