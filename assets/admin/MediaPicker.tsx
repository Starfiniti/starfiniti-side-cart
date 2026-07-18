import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { MediaSelection } from './types';

type Props = {
	id: number;
	url: string;
	label: string;
	onChange: ( selection: MediaSelection ) => void;
};

export function MediaPicker( { id, url, label, onChange }: Props ) {
	const choose = () => {
		const frame = window.wp?.media?.( {
			button: { text: __( 'Use image', 'starfiniti-cart' ) },
			library: { type: 'image' },
			multiple: false,
			title: label,
		} );

		if ( ! frame ) {
			return;
		}

		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			onChange( {
				id: Number( attachment.id ),
				url: attachment.sizes?.medium?.url ?? attachment.url,
			} );
		} );
		frame.open();
	};

	return (
		<div className="sfcart-media-picker">
			<p className="sfcart-field-label">{ label }</p>
			{ url ? (
				<img src={ url } alt="" />
			) : (
				<div aria-hidden="true">＋</div>
			) }
			<div className="sfcart-media-picker__actions">
				<Button variant="secondary" onClick={ choose }>
					{ id
						? __( 'Replace image', 'starfiniti-cart' )
						: __( 'Choose image', 'starfiniti-cart' ) }
				</Button>
				{ id > 0 && (
					<Button
						isDestructive
						variant="tertiary"
						onClick={ () => onChange( { id: 0, url: '' } ) }
					>
						{ __( 'Remove', 'starfiniti-cart' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
