import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	expect: { timeout: 20_000 },
	fullyParallel: false,
	reporter: process.env.CI ? 'github' : 'list',
	retries: process.env.CI ? 1 : 0,
	testDir: './tests/e2e',
	timeout: 60_000,
	workers: 1,
	use: {
		baseURL: 'http://127.0.0.1:9400',
		navigationTimeout: 90_000,
		trace: 'retain-on-failure',
	},
	webServer: {
		command: 'npm run playground:ci',
		reuseExistingServer: ! process.env.CI,
		timeout: 360_000,
		url: 'http://127.0.0.1:9400/wp-includes/css/sfcart-playground-ready.css',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
