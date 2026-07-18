export const SFCART_EVENTS = {
	closed: 'sfcart:closed',
	opened: 'sfcart:opened',
	updated: 'sfcart:updated',
} as const;

export type SfcartEventName =
	( typeof SFCART_EVENTS )[ keyof typeof SFCART_EVENTS ];

export function dispatchCartEvent(
	eventName: SfcartEventName,
	detail: Record< string, unknown > = {}
): void {
	document.dispatchEvent( new CustomEvent( eventName, { detail } ) );
}
