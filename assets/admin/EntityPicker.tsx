import { Button, Spinner, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

import { errorMessage } from './api';
import type { SearchItem } from './types';

type Props< T extends SearchItem > = {
	label: string;
	selected: number[];
	search: ( query: string, include?: number[] ) => Promise< T[] >;
	display: ( item: T ) => string;
	meta: ( item: T ) => string;
	onChange: ( identifiers: number[] ) => void;
	limit?: number;
	single?: boolean;
};

export function EntityPicker< T extends SearchItem >( {
	label,
	selected,
	search,
	display,
	meta,
	onChange,
	limit = 20,
	single = false,
}: Props< T > ) {
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState< T[] >( [] );
	const [ known, setKnown ] = useState< T[] >( [] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const selectedKey = selected.join( ',' );

	useEffect( () => {
		if ( selected.length === 0 ) {
			return;
		}

		void search( '', selected )
			.then( setKnown )
			.catch( () => undefined );
		// selectedKey intentionally represents the stable identifier set.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ selectedKey ] );

	const selectedItems = useMemo(
		() =>
			selected.map( ( id ) => known.find( ( item ) => item.id === id ) ),
		[ known, selected ]
	);

	const runSearch = async () => {
		setBusy( true );
		setError( '' );
		try {
			const items = await search( query );
			setResults( items );
			setKnown( ( current ) => [
				...current.filter(
					( item ) =>
						! items.some( ( result ) => result.id === item.id )
				),
				...items,
			] );
		} catch ( requestError ) {
			setError( errorMessage( requestError ) );
		} finally {
			setBusy( false );
		}
	};

	const add = ( identifier: number ) => {
		onChange(
			single
				? [ identifier ]
				: Array.from( new Set( [ ...selected, identifier ] ) ).slice(
						0,
						limit
				  )
		);
	};

	return (
		<div className="sfcart-entity-picker">
			<div className="sfcart-entity-picker__search">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					value={ query }
					onChange={ setQuery }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							void runSearch();
						}
					} }
				/>
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ runSearch }
				>
					{ __( 'Search', 'starfiniti-cart' ) }
				</Button>
			</div>
			{ busy && <Spinner /> }
			{ error && <p className="sfcart-field-error">{ error }</p> }
			{ results.length > 0 && (
				<ul className="sfcart-search-results">
					{ results.map( ( item ) => {
						const isSelected = selected.includes( item.id );
						return (
							<li key={ item.id }>
								<span>
									<strong>{ display( item ) }</strong>
									<small>{ meta( item ) }</small>
								</span>
								<Button
									variant="tertiary"
									disabled={ isSelected }
									onClick={ () => add( item.id ) }
								>
									{ isSelected
										? __( 'Selected', 'starfiniti-cart' )
										: __( 'Add', 'starfiniti-cart' ) }
								</Button>
							</li>
						);
					} ) }
				</ul>
			) }
			{ selected.length > 0 && (
				<div className="sfcart-selected-entities">
					<p className="sfcart-field-label">
						{ sprintf(
							/* translators: %d is the number of selected objects. */
							__( 'Selected (%d)', 'starfiniti-cart' ),
							selected.length
						) }
					</p>
					{ selected.map( ( id, index ) => (
						<span className="sfcart-selected-chip" key={ id }>
							{ selectedItems[ index ]
								? display( selectedItems[ index ] as T )
								: `#${ id }` }
							<Button
								label={ __(
									'Remove selection',
									'starfiniti-cart'
								) }
								variant="tertiary"
								onClick={ () =>
									onChange(
										selected.filter(
											( current ) => current !== id
										)
									)
								}
							>
								×
							</Button>
						</span>
					) ) }
				</div>
			) }
		</div>
	);
}
