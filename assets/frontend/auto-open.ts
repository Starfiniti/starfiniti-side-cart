import type { AutoOpenCookie } from './types';

/**
 * Read and delete the one-shot flag left by a classic (page reload) add to
 * cart. The flag lives in a short-lived cookie rather than page HTML, so full
 * page caches never replay it to other visitors. It is always deleted, even
 * when the current page must not open the drawer.
 *
 * @param doc    Document whose cookies are inspected.
 * @param cookie Server-provided cookie description.
 */
export function consumeAutoOpenFlag(
	doc: Document,
	cookie: AutoOpenCookie | undefined
): boolean {
	if ( ! cookie?.name ) {
		return false;
	}

	const present = doc.cookie
		.split( ';' )
		.some( ( entry ) => entry.trim() === `${ cookie.name }=1` );

	if ( ! present ) {
		return false;
	}

	const attributes = [
		`${ cookie.name }=`,
		'Max-Age=0',
		`path=${ cookie.path || '/' }`,
		'SameSite=Lax',
	];
	if ( cookie.domain ) {
		attributes.push( `domain=${ cookie.domain }` );
	}
	doc.cookie = attributes.join( '; ' );

	return true;
}
