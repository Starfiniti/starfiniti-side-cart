import { useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { ShoppingCart } from 'lucide-react';

import metadata from '../../../blocks/cart-toggle/block.json';

function Edit(): React.JSX.Element {
	const blockProps = useBlockProps( {
		className: 'sfcart-block-toggle',
	} );

	return (
		<div { ...blockProps }>
			<button className="sfcart-toggle" type="button" disabled>
				<span className="sfcart-toggle__icon" aria-hidden="true">
					<ShoppingCart size={ 24 } strokeWidth={ 1.8 } />
				</span>
				<span className="sfcart-toggle__count">0</span>
				<span className="sfcart-screen-reader-text">
					{ __( 'Open cart', 'starfiniti-cart' ) }
				</span>
			</button>
		</div>
	);
}

registerBlockType( metadata.name, {
	attributes: metadata.attributes,
	edit: Edit,
	save: () => null,
} );
