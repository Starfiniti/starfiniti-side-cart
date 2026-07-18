import { OperationQueue } from '../../assets/frontend/operation-queue';

function deferred(): {
	promise: Promise< void >;
	resolve: () => void;
} {
	let resolve = (): void => undefined;
	const promise = new Promise< void >( ( done ) => {
		resolve = done;
	} );
	return { promise, resolve };
}

describe( 'side-cart operation queue', () => {
	it( 'preserves invocation order across reads and mutations', async () => {
		const queue = new OperationQueue();
		const gate = deferred();
		const order: string[] = [];

		const first = queue.enqueue( async () => {
			order.push( 'mutation-start' );
			await gate.promise;
			order.push( 'mutation-end' );
		} );
		const refresh = queue.enqueueRefresh( async () => {
			order.push( 'refresh' );
		} );
		const second = queue.enqueue( async () => {
			order.push( 'second-mutation' );
		} );

		await Promise.resolve();
		expect( order ).toEqual( [ 'mutation-start' ] );
		gate.resolve();
		await Promise.all( [ first, refresh, second ] );
		expect( order ).toEqual( [
			'mutation-start',
			'mutation-end',
			'refresh',
			'second-mutation',
		] );
	} );

	it( 'coalesces refreshes that are waiting to execute', async () => {
		const queue = new OperationQueue();
		const gate = deferred();
		const blocker = queue.enqueue( () => gate.promise );
		const first = queue.enqueueRefresh( async () => undefined );
		const duplicate = queue.enqueueRefresh( async () => undefined );

		expect( first ).not.toBeNull();
		expect( duplicate ).toBeNull();
		gate.resolve();
		await Promise.all( [ blocker, first ] );
	} );

	it( 'allows one trailing refresh while a refresh is active', async () => {
		const queue = new OperationQueue();
		const gate = deferred();
		const calls: string[] = [];
		const active = queue.enqueueRefresh( async () => {
			calls.push( 'active' );
			await gate.promise;
		} );

		await Promise.resolve();
		const trailing = queue.enqueueRefresh( async () => {
			calls.push( 'trailing' );
		} );
		expect( trailing ).not.toBeNull();
		gate.resolve();
		await Promise.all( [ active, trailing ] );
		expect( calls ).toEqual( [ 'active', 'trailing' ] );
	} );
} );
