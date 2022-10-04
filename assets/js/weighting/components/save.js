/**
 * WordPress dependencies.
 */
import { Button, Panel, PanelBody, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { undo } from '@wordpress/icons';

export default ({ isBusy, isChanged, onReset }) => {
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
