import ReactDOM from 'react-dom';
import { AppContext } from './context';
import SynonymsEditor from './components/SynonymsEditor';

const SELECTOR = '#synonym-root';

/**
 * Get Root.
 *
 * @returns {string}
 */
const getRoot = () => document.querySelector(SELECTOR) || false;

ReactDOM.render(
	<AppContext>
		<SynonymsEditor />
	</AppContext>,
	getRoot(),
);
