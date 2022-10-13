/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody, PanelRow } from '@wordpress/components';
import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { undo } from '@wordpress/icons';

/**
 * Actions component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.isBusy Is the app busy?
 * @param {boolean} props.isChanged Are there changes?
 * @param {Function} props.onReset Reset handler.
 * @returns {WPElement} Component element.
 */
export default ({ isBusy, isChanged, onReset }) => {
	/**
	 * Render.
	 */
	return (
		<Panel>
			<PanelBody>
				<PanelRow>
					<div>
						<Button disabled={isBusy} isBusy={isBusy} isPrimary type="submit">
							{__('Save Changes', 'elasticpress')}
						</Button>
					</div>
					<Button
						icon={undo}
						isSecondary
						onClick={onReset}
						disabled={!isChanged}
						type="reset"
					>
						{__('Undo All Changes', 'elasticpress')}
					</Button>
				</PanelRow>
			</PanelBody>
		</Panel>
	);
};
