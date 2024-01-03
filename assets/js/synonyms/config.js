/**
 * Internal dependencies.
 */
import { mapEntry } from './utils';

/**
 * Window dependencies.
 */
const { alternatives, initialMode, sets } = window.epSynonyms.data;

const defaultIsSolr = initialMode === 'advanced';
const defaultSets = sets ? sets.map(mapEntry) : [mapEntry()];
const defaultAlternatives = alternatives ? alternatives.map(mapEntry) : [mapEntry()];

export { defaultIsSolr, defaultAlternatives, defaultSets };
