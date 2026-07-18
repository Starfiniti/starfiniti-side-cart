import {
	Button,
	Card,
	CardBody,
	Notice,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';

import { errorMessage, loadMigrationPreview, runMigration } from './api';
import type { AdminConfig, MigrationPreview } from './types';

type Props = {
	config: AdminConfig;
	onSettingsChanged: () => Promise< void >;
};

export function MigrationTool( { config, onSettingsChanged }: Props ) {
	const [ preview, setPreview ] = useState< MigrationPreview | null >( null );
	const [ busy, setBusy ] = useState( true );
	const [ confirmed, setConfirmed ] = useState( false );
	const [ phrase, setPhrase ] = useState( '' );
	const [ notice, setNotice ] = useState< {
		status: 'error' | 'success' | 'info';
		message: string;
	} | null >( null );

	const reload = useCallback( async () => {
		setBusy( true );
		setNotice( null );
		try {
			setPreview( await loadMigrationPreview( config ) );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setBusy( false );
		}
	}, [ config ] );

	useEffect( () => {
		void reload();
	}, [ reload ] );

	const canRun = confirmed && phrase.trim().toUpperCase() === 'MIGRATE';

	const migrate = async () => {
		if ( ! canRun ) {
			setNotice( {
				status: 'error',
				message: __(
					'Confirm the migration and type MIGRATE before running it.',
					'starfiniti-cart'
				),
			} );
			return;
		}

		setBusy( true );
		setNotice( null );
		try {
			let response = await runMigration( config );
			while ( response.result.status === 'running' ) {
				setPreview( response.preview );
				response = await runMigration( config );
			}
			setPreview( response.preview );
			await onSettingsChanged();
			setNotice( {
				status:
					response.result.status === 'complete' ? 'success' : 'error',
				message:
					response.result.status === 'complete'
						? __(
								'FunnelKit Cart data was copied into Starfiniti-owned storage.',
								'starfiniti-cart'
						  )
						: response.result.message ||
						  __(
								'The migration did not complete.',
								'starfiniti-cart'
						  ),
			} );
		} catch ( error ) {
			setNotice( { status: 'error', message: errorMessage( error ) } );
		} finally {
			setBusy( false );
		}
	};

	return (
		<Card>
			<CardBody>
				<h3>{ __( 'FunnelKit Cart migration', 'starfiniti-cart' ) }</h3>
				<p>
					{ __(
						'Preview and copy legacy side-cart settings plus cart analytics into Starfiniti-owned storage. Legacy FunnelKit data is left untouched, so rollback is deactivating Starfiniti Cart and reactivating FunnelKit Cart.',
						'starfiniti-cart'
					) }
				</p>

				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }

				{ busy && ! preview && <Spinner /> }

				{ preview && (
					<>
						<div className="sfcart-migration-stats">
							<div>
								<span>
									{ __(
										'Settings keys detected',
										'starfiniti-cart'
									) }
								</span>
								<strong>
									{ preview.legacy_settings_keys.length }
								</strong>
							</div>
							<div>
								<span>
									{ __(
										'Legacy order rows',
										'starfiniti-cart'
									) }
								</span>
								<strong>
									{ preview.table_counts.cart_rows }
								</strong>
							</div>
							<div>
								<span>
									{ __(
										'Legacy item rows',
										'starfiniti-cart'
									) }
								</span>
								<strong>
									{ preview.table_counts.product_rows }
								</strong>
							</div>
						</div>

						{ preview.warnings.length > 0 && (
							<Notice status="info" isDismissible={ false }>
								{ preview.warnings.join( ' ' ) }
							</Notice>
						) }

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'I understand this copies legacy data and does not delete FunnelKit data.',
								'starfiniti-cart'
							) }
							checked={ confirmed }
							onChange={ setConfirmed }
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Type MIGRATE to confirm',
								'starfiniti-cart'
							) }
							value={ phrase }
							onChange={ setPhrase }
						/>

						<div className="sfcart-admin-row-actions">
							<Button
								variant="secondary"
								disabled={ busy }
								onClick={ reload }
							>
								{ __( 'Refresh preview', 'starfiniti-cart' ) }
							</Button>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={
									busy || ! preview.available || ! canRun
								}
								onClick={ migrate }
							>
								{ preview.status.state.status === 'running'
									? __(
											'Resume migration',
											'starfiniti-cart'
									  )
									: __( 'Run migration', 'starfiniti-cart' ) }
							</Button>
						</div>

						{ preview.status.audit.length > 0 && (
							<div className="sfcart-migration-audit">
								<h4>
									{ __( 'Audit log', 'starfiniti-cart' ) }
								</h4>
								<ul>
									{ preview.status.audit
										.slice()
										.reverse()
										.map( ( entry ) => (
											<li
												key={ `${
													entry.id ?? 'migration'
												}-${
													entry.completed_at ??
													entry.started_at
												}` }
											>
												<strong>
													{ entry.status }
												</strong>{ ' ' }
												{ entry.completed_at } —{ ' ' }
												{ sprintf(
													/* translators: 1: imported conversion count, 2: imported item count. */
													__(
														'%1$d conversions, %2$d items',
														'starfiniti-cart'
													),
													entry.conversions_imported ??
														0,
													entry.items_imported ?? 0
												) }
											</li>
										) ) }
								</ul>
							</div>
						) }
					</>
				) }
			</CardBody>
		</Card>
	);
}
