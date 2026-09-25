export function clampQuantity(
	quantity: number,
	minimum: number,
	maximum: number | null
): number {
	const lowerBound = Math.max( minimum, quantity );

	return maximum === null ? lowerBound : Math.min( maximum, lowerBound );
}

export function formatTemplate(
	template: string,
	values: Array< string | number >
): string {
	let formatted = template;

	values.forEach( ( value, index ) => {
		formatted = formatted
			.replace( `%${ index + 1 }$s`, String( value ) )
			.replace( `%${ index + 1 }$d`, String( value ) )
			.replace( '%s', String( value ) );
	} );

	return formatted.replaceAll( '%%', '%' );
}

/**
 * Return the server-pluralized item count, falling back to the legacy
 * singular/plural labels for state payloads that predate it.
 *
 * @param count        Number of items in the cart.
 * @param serverLabel  Ready-to-display label from the cart state.
 * @param labels       Legacy singular and plural words.
 * @param labels.item  Legacy singular word.
 * @param labels.items Legacy plural word.
 */
export function itemCountLabel(
	count: number,
	serverLabel: string | undefined,
	labels: { item: string; items: string }
): string {
	if ( serverLabel ) {
		return serverLabel;
	}

	return `${ count } ${ count === 1 ? labels.item : labels.items }`;
}

export function createElement< K extends keyof HTMLElementTagNameMap >(
	tagName: K,
	className = '',
	text = ''
): HTMLElementTagNameMap[ K ] {
	const element = document.createElement( tagName );

	if ( className ) {
		element.className = className;
	}

	if ( text ) {
		element.textContent = text;
	}

	return element;
}
