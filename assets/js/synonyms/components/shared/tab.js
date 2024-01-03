import { Flex, FlexItem, Icon, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import error from '../../../sync-ui/components/icons/error';

export default ({ isInvalid, children }) => {
	return (
		<Flex align="center" justify="start">
			<FlexItem>{children}</FlexItem>
			{isInvalid ? (
				<FlexItem>
					<Tooltip text={__('This group has errors', 'elasticpress')}>
						{/* eslint-disable-next-line react/jsx-no-useless-fragment */}
						<>
							<Icon icon={error} />
						</>
					</Tooltip>
				</FlexItem>
			) : null}
		</Flex>
	);
};
