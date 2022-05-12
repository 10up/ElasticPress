import edit from './edit';
import block from './block.json';

const { registerBlockType } = wp.blocks;

registerBlockType(block, {
	edit,
	save: () => {},
});
