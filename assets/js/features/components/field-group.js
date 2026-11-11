/**
 * WordPress dependencies.
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { useState, WPElement } from '@wordpress/element';
import { chevronDown, chevronUp } from '@wordpress/icons';

/**
 * A box of feature settings. With `collapsible` set in the feature's field group map, its
 * header opens and closes it; `initialOpen` (default true) sets the first state. A `badge`
 * shows a short status next to the label, so a closed box can still say it ("Not set up").
 *
 * @param {object} props Component props.
 * @param {object} props.group Field group: label, collapsible, initialOpen, badge.
 * @param {WPElement} props.children Controls.
 * @returns {WPElement} Field group.
 */
export default ({ group, children }) => {
	const collapsible = Boolean(group?.collapsible);
	const [isOpen, setIsOpen] = useState(!collapsible || group?.initialOpen !== false);
	const bodyId = `ep-field-group-${useInstanceId(Card)}`;
	const badge = group?.badge ? (
		<span className="ep-field-group__badge">{group.badge}</span>
	) : null;

	return (
		<div className={`ep-field-group${collapsible ? ' ep-field-group--collapsible' : ''}`}>
			<Card>
				{group &&
					(collapsible ? (
						<CardHeader className="ep-field-group__header">
							<Button
								aria-controls={bodyId}
								aria-expanded={isOpen}
								className="ep-field-group__toggle"
								icon={isOpen ? chevronUp : chevronDown}
								iconPosition="right"
								onClick={() => setIsOpen(!isOpen)}
							>
								<strong>{group.label}</strong>
								{badge}
							</Button>
						</CardHeader>
					) : (
						<CardHeader>
							<strong>{group.label}</strong>
							{badge}
						</CardHeader>
					))}
				{isOpen ? <CardBody id={bodyId}>{children}</CardBody> : null}
			</Card>
		</div>
	);
};
