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
