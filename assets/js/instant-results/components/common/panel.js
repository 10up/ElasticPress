/**
 * WordPress dependencies.
 */
import { useState, WPElement } from '@wordpress/element';
import { chevronDown, chevronUp, Icon } from '@wordpress/icons';

/**
 * Facet wrapper component.
 *
 * @param {object} props Component props.
 * @param {WPElement} props.children Component children.
 * @param {boolean} props.defaultIsOpen Whether the panel is open by default.
 * @param {string} props.label Facet label.
 * @returns {WPElement} Component element.
 */
export default ({ children, defaultIsOpen, label }) => {
	const [isOpen, setIsOpen] = useState(defaultIsOpen);

	/**
	 * Handle click event on the header.
	 */
	const onClick = () => {
		setIsOpen(!isOpen);
	};

	return (
		<div className="ep-search-panel">
			<h3 className="ep-search-panel__heading">
				<button
					aria-expanded={isOpen}
					className="ep-search-panel__button ep-search-reset-button ep-search-icon-button"
					onClick={onClick}
					type="button"
				>
					{label}
					<Icon icon={isOpen ? chevronUp : chevronDown} />
				</button>
			</h3>
			<div aria-hidden={!isOpen} className="ep-search-panel__content">
				{children(isOpen)}
			</div>
		</div>
	);
};
