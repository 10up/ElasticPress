// wrapper component that allows indentation and spacing.
import { Spacer } from '@wordpress/components';

export default ({ children, indent = false }) => {
	return (
		<Spacer paddingLeft={indent ? 6.3 : 0} paddingY={2} paddingRight={20}>
			{children}
		</Spacer>
	);
};
