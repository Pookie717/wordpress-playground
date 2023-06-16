import { createRoot } from 'react-dom/client';
import PlaygroundViewport from './components/playground-viewport';
import ExportButton from './components/export-button';
import ImportButton from './components/import-button';
import './styles.css';

import { makeBlueprint } from './lib/make-blueprint';
import type { Blueprint } from '@wp-playground/blueprints';
import { PlaygroundClient } from '@wp-playground/remote';
import SiteSetupButton from './components/site-setup-button';
import Button from './components/button';

const query = new URL(document.location.href).searchParams;

/*
 * Support passing blueprints in the URI fragment, e.g.:
 * /#{"landingPage": "/?p=4"}
 */
const fragment = decodeURI(document.location.hash || '#').substring(1);
let blueprint: Blueprint;
try {
	blueprint = JSON.parse(fragment);
	// Allow overriding the preferred versions using query params
	// generated by the version switchers.
	if (query.get('php') || query.get('wp')) {
		if (!blueprint.preferredVersions) {
			blueprint.preferredVersions = {} as any;
		}
		blueprint.preferredVersions!.php =
			(query.get('php') as any) ||
			blueprint.preferredVersions!.php ||
			'8.0';
		blueprint.preferredVersions!.wp =
			query.get('wp') || blueprint.preferredVersions!.wp || 'latest';
	}
} catch (e) {
	blueprint = makeBlueprint({
		php: query.get('php') || '8.0',
		wp: query.get('wp') || 'latest',
		theme: query.get('theme') || undefined,
		plugins: query.getAll('plugin'),
		landingPage: query.get('url') || undefined,
		gutenbergPR: query.has('gutenberg-pr')
			? Number(query.get('gutenberg-pr'))
			: undefined,
	});
}

const isSeamless = (query.get('mode') || 'browser') === 'seamless';

// @ts-ignore
const opfsSupported = typeof navigator?.storage?.getDirectory !== 'undefined';
const persistent = query.get('persistent') === '1' && opfsSupported;
const root = createRoot(document.getElementById('root')!);
root.render(
	<PlaygroundViewport
		persistent={persistent}
		isSeamless={isSeamless}
		blueprint={blueprint}
		toolbarButtons={[
			<SiteSetupButton
				persistent={persistent}
				selectedPHP={blueprint.preferredVersions?.php}
				preferredWP={blueprint.preferredVersions?.wp}
			/>,
			persistent && <OpfsResetButton />,
			<ImportButton key="export" />,
			<ExportButton key="export" />,
		]}
	/>
);

function OpfsResetButton({ playground }: { playground?: PlaygroundClient }) {
	return (
		<Button
			onClick={async () => {
				if (
					!window.confirm(
						'This will wipe out all data and start a new site. Do you want to proceed?'
					)
				) {
					return;
				}
				if (persistent) {
					await playground?.resetOpfs();
				}
				window.location.reload();
			}}
		>
			Start over
		</Button>
	);
}
