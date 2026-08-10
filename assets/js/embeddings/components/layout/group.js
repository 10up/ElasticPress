// wrapper component that allows indentation and spacing.
import { __experimentalSpacer as Spacer } from '@wordpress/components'; // eslint-disable-line @wordpress/no-unsafe-wp-apis

export default ({ children, indent = false }) => {
	return (
		<Spacer paddingLeft={indent ? 6.3 : 0} paddingY={2} paddingRight={20}>
			{children}
		</Spacer>
	);
};
