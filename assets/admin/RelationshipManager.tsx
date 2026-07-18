import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

import { errorMessage, loadRelationships, saveRelationships } from './api';
import { EntityPicker } from './EntityPicker';
import type { AdminConfig, ProductSummary } from './types';

type Props = {
	config: AdminConfig;
	search: (
		query: string,
		include?: number[]
	) => Promise< ProductSummary[] >;
};

export function RelationshipManager( { config, search }: Props ) {
	const [ selectedProduct, setSelectedProduct ] = useState< number[] >( [] );
	const [ upsells, setUpsells ] = useState< number[] >( [] );
	const [ crossSells, setCrossSells ] = useState< number[] >( [] );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState< {
		status: 'error' | 'success';
		message: string;
	} | null >( null );
	const productId = selectedProduct[ 0 ] ?? 0;

	useEffect( () => {
		if ( productId === 0 ) {
			setUpsells( [] );
			setCrossSells( [] );
			return;
		}

		setBusy( true );
		setNotice( null );
		void loadRelationships( config, productId )
			.then( ( response ) => {
				setUpsells( response.upsells.map( ( product ) => product.id ) );
				setCrossSells(
					response.cross_sells.map( ( product ) => product.id )
				);
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: errorMessage( error ) } )
			)
			.finally( () => setBusy( false ) );
	}, [ config, productId ] );

	const save = async () => {
		if ( productId === 0 ) {
			return;
		}

		setBusy( true );
		setNotice( null );
		try {
			const response = await saveRelationships(
				config,
				productId,
				upsells,
				crossSells
			);
			setUpsells( response.upsells.map( ( product ) => product.id ) );
			setCrossSells(
				response.cross_sells.map( ( product ) => product.id )
			);
			setNotice( {
				status: 'success',
				message: __(
					'Product relationships saved.',
					'starfiniti-cart'
				),
			} );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setBusy( false );
		}
	};

	const display = ( product: ProductSummary ) => product.name;
	const meta = ( product: ProductSummary ) =>
		[ product.sku, product.price ].filter( Boolean ).join( ' · ' );

	return (
		<div className="sfcart-relationship-manager">
			<h3>{ __( 'Product relationship manager', 'starfiniti-cart' ) }</h3>
			<p>
				{ __(
					'Edit WooCommerce-native upsells and cross-sells from one place.',
					'starfiniti-cart'
				) }
			</p>
			<EntityPicker< ProductSummary >
				label={ __( 'Search product to manage', 'starfiniti-cart' ) }
				selected={ selectedProduct }
				search={ search }
				display={ display }
				meta={ meta }
				onChange={ setSelectedProduct }
				single
			/>
			{ busy && <Spinner /> }
			{ notice && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.message }
				</Notice>
			) }
			{ productId > 0 && ! busy && (
				<>
					<EntityPicker< ProductSummary >
						label={ __( 'Search upsells', 'starfiniti-cart' ) }
						selected={ upsells }
						search={ search }
						display={ display }
						meta={ meta }
						onChange={ setUpsells }
						limit={ 50 }
					/>
					<EntityPicker< ProductSummary >
						label={ __( 'Search cross-sells', 'starfiniti-cart' ) }
						selected={ crossSells }
						search={ search }
						display={ display }
						meta={ meta }
						onChange={ setCrossSells }
						limit={ 50 }
					/>
					<Button
						className="sfcart-relationship-manager__save"
						variant="primary"
						onClick={ save }
						disabled={ busy }
					>
						{ __(
							'Save product relationships',
							'starfiniti-cart'
						) }
					</Button>
				</>
			) }
		</div>
	);
}
