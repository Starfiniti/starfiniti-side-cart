export type RecommendationLayout = 'style1' | 'style2' | 'style3' | 'carousel';

/**
 * Keep layout capacity independent from the broader recommendation limit.
 *
 * @param layout          Selected recommendation layout.
 * @param configuredLimit Merchant-configured display limit.
 */
export function recommendationDisplayLimit(
	layout: RecommendationLayout,
	configuredLimit: number
): number {
	if ( layout === 'style1' ) {
		return 1;
	}

	const normalizedLimit = Math.max( 1, Math.floor( configuredLimit ) );

	if ( layout === 'style2' ) {
		return Math.min( 3, normalizedLimit );
	}

	return normalizedLimit;
}
