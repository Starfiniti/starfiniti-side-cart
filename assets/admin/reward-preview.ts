import type { RewardSettings } from './types';

export type RewardPreviewMilestone = {
	id: string;
	label: string;
	message: string;
	position: number;
	achieved: boolean;
};

export type RewardPreviewState = {
	enabled: boolean;
	design: RewardSettings[ 'progress_design' ];
	progress: number;
	complete: boolean;
	message: string;
	milestones: RewardPreviewMilestone[];
};

type RewardLabels = Record<
	RewardSettings[ 'milestones' ][ number ][ 'type' ],
	string
>;

const expandMessage = (
	template: string,
	label: string,
	remaining: number,
	formatMoney: ( value: number ) => string
) =>
	template
		.replaceAll( '{{remaining_amount}}', formatMoney( remaining ) )
		.replaceAll( '{{reward}}', label );

export function buildRewardPreviewState(
	settings: RewardSettings,
	amount: number,
	formatMoney: ( value: number ) => string,
	labels: RewardLabels
): RewardPreviewState {
	const active = settings.milestones
		.filter( ( milestone ) => milestone.enabled )
		.sort( ( first, second ) => first.threshold - second.threshold );
	const maximum = active.reduce(
		( current, milestone ) => Math.max( current, milestone.threshold ),
		0
	);
	let progress = 0;
	if ( active.length > 0 ) {
		progress =
			maximum > 0
				? Math.min( 100, Math.max( 0, ( amount / maximum ) * 100 ) )
				: 100;
	}
	let nextMessage = '';

	const milestones = active.map( ( milestone ) => {
		const achieved = amount >= milestone.threshold;
		const remaining = Math.max( 0, milestone.threshold - amount );
		const label = milestone.label.trim() || labels[ milestone.type ];
		const message = expandMessage(
			achieved ? milestone.achieved_message : milestone.pending_message,
			label,
			remaining,
			formatMoney
		);

		if ( ! achieved && nextMessage === '' ) {
			nextMessage = message;
		}

		return {
			id: milestone.id,
			label,
			message,
			position:
				maximum > 0
					? Math.min( 100, ( milestone.threshold / maximum ) * 100 )
					: 100,
			achieved,
		};
	} );

	const complete = milestones.length > 0 && nextMessage === '';

	return {
		enabled: settings.enabled && milestones.length > 0,
		design: settings.progress_design,
		progress,
		complete,
		message: complete ? settings.complete_message : nextMessage,
		milestones,
	};
}
