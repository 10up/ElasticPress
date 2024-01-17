/**
 * WordPress dependencies.
 */
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { WPElement } from '@wordpress/element';

/**
 * Internal dependencies/
 */
import error from '../icons/error';

/**
 * Group tab component.
 *
 * Adds an icon with a tooltio if the group contains invalid sets.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {boolean} props.isValid Whether the group is valid.
 * @returns {WPElement}
 */
export default ({ children, isValid }) => {
	return (
		<Flex align="center" justify="start">
			<FlexItem>{children}</FlexItem>
			{!isValid ? (
				<FlexItem>
					<Icon icon={error} />
				</FlexItem>
			) : null}
		</Flex>
	);
};
