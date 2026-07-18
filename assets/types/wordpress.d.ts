declare module '@wordpress/block-editor' {
	import type { HTMLAttributes } from 'react';

	export function useBlockProps(
		properties?: HTMLAttributes< HTMLDivElement >
	): HTMLAttributes< HTMLDivElement >;
}

declare module '@wordpress/blocks' {
	import type { ReactElement } from 'react';

	type BlockConfiguration = {
		attributes?: Record< string, unknown >;
		edit: () => ReactElement;
		save: () => null;
	};

	export function registerBlockType(
		name: string,
		configuration: BlockConfiguration
	): unknown;
}

type SfcartMediaAttachment = {
	id: number;
	url: string;
	sizes?: Record< string, { url: string } >;
};

type SfcartMediaFrame = {
	on: ( event: 'select', callback: () => void ) => SfcartMediaFrame;
	open: () => void;
	state: () => {
		get: ( key: 'selection' ) => {
			first: () => { toJSON: () => SfcartMediaAttachment };
		};
	};
};

interface Window {
	sfcartAdminConfig?: import('../admin/types').AdminConfig;
	wp?: {
		media?: ( options: {
			button: { text: string };
			library: { type: 'image' };
			multiple: false;
			title: string;
		} ) => SfcartMediaFrame;
	};
}
