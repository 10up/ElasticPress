/**
 * WordPress dependencies.
 */
import {
	Button,
	Flex,
	FlexItem,
	FormTokenField,
	Notice,
	Panel,
	PanelBody,
	PanelHeader,
	TextControl,
} from '@wordpress/components';
import { forwardRef, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Edit panel component.
 *
 * @param {object} props Component props.
 * @param {boolean} props.disabled Is editing disabled?
 * @param {string} props.errorMessage Error message.
 * @param {boolean} props.isNew Is this a new rule?
 * @param {boolean} props.isValid Is the form valid?
 * @param {object} props.labels Labels.
 * @param {'hyponyms'|'replacements'|'synonyms'} props.mode Editing mode.
 * @param {Function} props.onChangePrimary Primary terms change handler.
 * @param {Function} props.onChangeSynonyms Synonyms change handler.
 * @param {Function} props.onReset Reset handler.
 * @param {Function} props.onSubmit Submit handler.
 * @param {string[]} props.primaryValue Primary term values.
 * @param {string[]} props.synonymsValue Synonyms values.
 * @param {object} ref Forwarded reference.
 * @returns {WPElement}
 */
const EditPanel = (
	{
		disabled,
		errorMessage,
		isNew,
		isValid,
		labels,
		mode,
		onChangePrimary,
		onChangeSynonyms,
		onReset,
		onSubmit,
		primaryValue,
		synonymsValue,
	},
	ref,
) => {
	return (
		<Panel className="ep-synonyms-edit-panel" ref={ref}>
			<PanelHeader>
				<h2>{isNew ? labels.new : labels.edit}</h2>
			</PanelHeader>
			<PanelBody>
				<form onSubmit={onSubmit}>
					{errorMessage ? (
						<Notice isDismissible={false} status="error">
							{errorMessage}
						</Notice>
					) : null}
					{mode === 'hyponyms' ? (
						<TextControl
							disabled={disabled}
							label={labels.primary}
							onChange={onChangePrimary}
							value={primaryValue.map((p) => p.value).join('')}
						/>
					) : null}
					{mode === 'replacements' ? (
						<FormTokenField
							disabled={disabled}
							label={labels.primary}
							onChange={onChangePrimary}
							value={primaryValue.map((p) => p.value)}
						/>
					) : null}
					<FormTokenField
						disabled={disabled}
						label={labels.synonyms}
						onChange={onChangeSynonyms}
						value={synonymsValue.map((h) => h.value)}
					/>
					<Flex justify="start">
						<FlexItem>
							<Button
								disabled={disabled || !isValid}
								type="submit"
								variant="secondary"
							>
								{isNew ? labels.add : __('Save changes', 'elasticpress')}
							</Button>
						</FlexItem>
						{!isNew ? (
							<FlexItem>
								<Button
									disabled={disabled}
									onClick={onReset}
									type="button"
									variant="tertiary"
								>
									{__('Cancel', 'elasticpress')}
								</Button>
							</FlexItem>
						) : null}
					</Flex>
				</form>
			</PanelBody>
		</Panel>
	);
};

export default forwardRef(EditPanel);
