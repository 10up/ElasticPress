/**
 * WordPress dependencies.
 */
import { Button, Flex, FlexItem, Icon, Tooltip } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { edit, trash } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import error from '../icons/error';

/**
 * List table row actions component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.disabled Are actions disabled?
 * @param {string} props.errorMessage Error message.
 * @param {boolean} props.isSelected Is the row selected?
 * @param {Function} props.onSelect Select handler.
 * @param {Function} props.onDelete Delete handler.
 * @returns {WPElement}
 */
export default ({ disabled, errorMessage, isSelected, onSelect, onDelete }) => {
	return (
		<Flex justify="end">
			<FlexItem>
				{errorMessage ? (
					<Tooltip delay={0} placement="bottom" text={errorMessage}>
						<div className="ep-synonyms-error-badge">
							<Icon icon={error} />
						</div>
					</Tooltip>
				) : null}
			</FlexItem>
			<FlexItem>
				<Button
					disabled={disabled}
					icon={edit}
					isPressed={isSelected}
					label={__('Edit', 'elasticpress')}
					onClick={onSelect}
				/>
			</FlexItem>
			<FlexItem>
				<Button
					disabled={disabled}
					icon={trash}
					label={__('Delete', 'elasticpress')}
					onClick={onDelete}
				/>
			</FlexItem>
		</Flex>
	);
};
