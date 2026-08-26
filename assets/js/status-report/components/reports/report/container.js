import { Panel, PanelHeader, Button, Notice } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { RawHTML } from '@wordpress/element';
import { safeHTML } from '@wordpress/dom';

export default ({ id, title, actions = [], messages = [], children }) => {
	return (
		<Panel id={title} className="ep-status-report">
			<PanelHeader>
				<h2 id={id}>{title}</h2>
				{actions.map(({ href, label }) => (
					<Button
						href={decodeEntities(href)}
						isDestructive
						isSecondary
						isSmall
						key={href}
					>
						{label}
					</Button>
				))}
			</PanelHeader>

			{messages.map(({ message, type }) => (
				<Notice status={type} isDismissible={false} key={message}>
					<RawHTML>{safeHTML(message)}</RawHTML>
				</Notice>
			))}

			{children}
		</Panel>
	);
};
