import { ZipArchive } from 'archiver';
import { createHash } from 'node:crypto';
import { createWriteStream } from 'node:fs';
import {
	mkdir,
	readFile,
	readdir,
	rm,
	stat,
	writeFile,
} from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const requestedDistDirectory = process.env.SFCART_DIST_DIR?.trim();
const distDirectory = requestedDistDirectory
	? path.resolve( requestedDistDirectory )
	: path.join( projectRoot, 'dist' );
const packageMetadata = JSON.parse(
	await readFile( path.join( projectRoot, 'package.json' ), 'utf8' )
);
const archiveName = `starfiniti-cart-${ packageMetadata.version }.zip`;
const archivePath = path.join( distDirectory, archiveName );
const checksumPath = `${ archivePath }.sha256`;
const fixedDate = new Date( '1980-01-01T00:00:00.000Z' );

const releaseRoots = [
	'assets',
	'blocks',
	'build',
	'docs',
	'CHANGELOG.md',
	'composer.json',
	'composer.lock',
	'LICENSE',
	'NOTICE.md',
	'package-lock.json',
	'package.json',
	'README.md',
	'scripts',
	'src',
	'starfiniti-cart.php',
	'tsconfig.json',
	'uninstall.php',
	'webpack.config.js',
];

async function collectFiles( relativePath ) {
	const absolutePath = path.join( projectRoot, relativePath );
	const entryStat = await stat( absolutePath );

	if ( entryStat.isFile() ) {
		return [ relativePath.replaceAll( '\\', '/' ) ];
	}

	const entries = ( await readdir( absolutePath ) ).sort();
	const descendants = await Promise.all(
		entries.map( ( entry ) =>
			collectFiles( path.join( relativePath, entry ) )
		)
	);

	return descendants.flat();
}

await mkdir( distDirectory, { recursive: true } );
await Promise.all( [
	rm( archivePath, { force: true } ),
	rm( checksumPath, { force: true } ),
] );

const files = (
	await Promise.all( releaseRoots.map( ( root ) => collectFiles( root ) ) )
)
	.flat()
	.sort();
const unexpectedRuntimeSources = files.filter(
	( relativePath ) =>
		relativePath.startsWith( 'src/' ) && ! relativePath.endsWith( '.php' )
);

if ( unexpectedRuntimeSources.length > 0 ) {
	throw new Error(
		`Unexpected non-PHP runtime source files:\n${ unexpectedRuntimeSources.join(
			'\n'
		) }`
	);
}

const releaseEntries = await Promise.all(
	files.map( async ( relativePath ) => ( {
		contents: await readFile( path.join( projectRoot, relativePath ) ),
		relativePath,
	} ) )
);

await new Promise( ( resolve, reject ) => {
	const output = createWriteStream( archivePath );
	const archive = new ZipArchive( {
		zlib: { level: 9 },
	} );

	output.on( 'close', resolve );
	output.on( 'error', reject );
	archive.on( 'error', reject );
	archive.pipe( output );

	for ( const { contents, relativePath } of releaseEntries ) {
		archive.append( contents, {
			date: fixedDate,
			mode: 0o100644,
			name: `starfiniti-cart/${ relativePath }`,
		} );
	}

	archive.finalize();
} );

const digest = createHash( 'sha256' )
	.update( await readFile( archivePath ) )
	.digest( 'hex' );
await writeFile( checksumPath, `${ digest }  ${ archiveName }\n`, 'utf8' );

process.stdout.write( `${ archivePath }\n${ checksumPath }\n` );
