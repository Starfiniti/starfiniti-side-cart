import { buildRewardPreviewState } from '../../assets/admin/reward-preview';
import type { RewardSettings } from '../../assets/admin/types';

const settings = {
	enabled: true,
	calculation_mode: 'subtotal',
	progress_design: 'bar',
	complete_message: 'All rewards unlocked!',
	allow_gift_removal: false,
	milestones: [
		{
			id: 'shipping-60',
			enabled: true,
			type: 'free_shipping',
			threshold: 60,
			label: '',
			pending_message:
				'Do brezplačne dostave vam še manjka {{remaining_amount}}.',
			achieved_message: '{{reward}}',
			coupon_id: 0,
			gift_product_id: 0,
			gift_variation_id: 0,
		},
	],
} satisfies RewardSettings;

const labels = {
	free_shipping: 'Free shipping',
	coupon: 'Discount',
	gift: 'Free gift',
};

describe( 'reward preview state', () => {
	it( 'uses the configured message and exactly one marker for one milestone', () => {
		const preview = buildRewardPreviewState(
			settings,
			42,
			( amount ) => `€${ amount.toFixed( 2 ) }`,
			labels
		);

		expect( preview.message ).toBe(
			'Do brezplačne dostave vam še manjka €18.00.'
		);
		expect( preview.progress ).toBe( 70 );
		expect( preview.milestones ).toHaveLength( 1 );
		expect( preview.milestones[ 0 ].position ).toBe( 100 );
		expect( preview.milestones[ 0 ].achieved ).toBe( false );
	} );

	it( 'shows the completion message only after every milestone is reached', () => {
		const preview = buildRewardPreviewState(
			settings,
			60,
			( amount ) => `€${ amount.toFixed( 2 ) }`,
			labels
		);

		expect( preview.complete ).toBe( true );
		expect( preview.message ).toBe( 'All rewards unlocked!' );
		expect( preview.milestones[ 0 ].achieved ).toBe( true );
	} );
} );
