/**
 * Serializes state reads and mutations so an older response can never render
 * after a newer cart mutation. Waiting refreshes are coalesced, while an event
 * received during an active refresh schedules one trailing refresh.
 */
export class OperationQueue {
	private tail: Promise< void > = Promise.resolve();
	private refreshQueued = false;

	public enqueue< T >( operation: () => Promise< T > ): Promise< T > {
		const result = this.tail.then( operation );
		this.tail = result.then(
			() => undefined,
			() => undefined
		);
		return result;
	}

	public enqueueRefresh< T >(
		operation: () => Promise< T >
	): Promise< T > | null {
		if ( this.refreshQueued ) {
			return null;
		}

		this.refreshQueued = true;
		return this.enqueue( async () => {
			this.refreshQueued = false;
			return operation();
		} );
	}
}
