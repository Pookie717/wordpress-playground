import { ProgressTracker } from '@php-wasm/progress';
import { Semaphore } from '@php-wasm/util';
import {
	LatestSupportedPHPVersion,
	SupportedPHPVersion,
	SupportedPHPVersions,
	UniversalPHP,
} from '@php-wasm/universal';
import { isFileReference, Resource } from './resources';
import { Step, StepDefinition } from './steps';
import * as stepHandlers from './steps/handlers';
import { Blueprint } from './blueprint';

export type CompiledStep = (php: UniversalPHP) => Promise<void> | void;

const supportedWordPressVersions = ['6.2', '6.1', '6.0', '5.9'] as const;
type supportedWordPressVersion = (typeof supportedWordPressVersions)[number];
export interface CompiledBlueprint {
	/** The requested versions of PHP and WordPress for the blueprint */
	versions: {
		php: SupportedPHPVersion;
		wp: supportedWordPressVersion;
	};
	/** The compiled steps for the blueprint */
	run: (playground: UniversalPHP) => Promise<void>;
}

export type OnStepCompleted = (output: any, step: StepDefinition) => any;

export interface CompileBlueprintOptions {
	/** Optional progress tracker to monitor progress */
	progress?: ProgressTracker;
	/** Optional semaphore to control access to a shared resource */
	semaphore?: Semaphore;
	/** Optional callback with step output */
	onStepCompleted?: OnStepCompleted;
}

/**
 * Compiles Blueprint into a form that can be executed.
 *
 * @param playground The PlaygroundClient to use for the compilation
 * @param blueprint The bBueprint to compile
 * @param options Additional options for the compilation
 * @returns The compiled blueprint
 */
export function compileBlueprint(
	blueprint: Blueprint,
	{
		progress = new ProgressTracker(),
		semaphore = new Semaphore({ concurrency: 3 }),
		onStepCompleted = () => {},
	}: CompileBlueprintOptions = {}
): CompiledBlueprint {
	const steps = (blueprint.steps || []).filter(isStepDefinition);

	const totalProgressWeight = steps.reduce(
		(total, step) => total + (step.progress?.weight || 1),
		0
	);
	const compiled = steps.map((step) =>
		compileStep(step, {
			semaphore,
			rootProgressTracker: progress,
			totalProgressWeight,
		})
	);

	return {
		versions: {
			php: compileVersion(
				blueprint.preferredVersions?.php,
				SupportedPHPVersions,
				LatestSupportedPHPVersion
			),
			wp: compileVersion(
				blueprint.preferredVersions?.wp,
				supportedWordPressVersions,
				'6.2'
			),
		},
		run: async (playground: UniversalPHP) => {
			try {
				// Start resolving resources early
				for (const { asyncResources } of compiled) {
					for (const resource of asyncResources) {
						resource.resolve();
					}
				}

				for (const { run, step } of compiled) {
					const result = await run(playground);
					onStepCompleted(result, step);
				}
				if ('goTo' in playground) {
					await (playground as any).goTo(
						blueprint.landingPage || '/'
					);
				}
			} finally {
				progress.finish();
			}
		},
	};
}

/**
 * Compiles a preferred version string into a supported version
 *
 * @param value The value to compile
 * @param supported The list of supported versions
 * @param latest The latest supported version
 * @returns The compiled version
 */
function compileVersion<T>(
	value: string | undefined | null,
	supported: readonly T[],
	latest: string
): T {
	if (value && supported.includes(value as any)) {
		return value as T;
	}
	return latest as T;
}

/**
 * Determines if a step is a StepDefinition object
 *
 * @param step The object to test
 * @returns Whether the object is a StepDefinition
 */
function isStepDefinition(
	step: Step | string | undefined | false | null
): step is StepDefinition {
	return !!(typeof step === 'object' && step);
}

interface CompileStepArgsOptions {
	/** Optional semaphore to control access to a shared resource */
	semaphore?: Semaphore;
	/** The root progress tracker for the compilation */
	rootProgressTracker: ProgressTracker;
	/** The total progress weight of all the steps in the blueprint */
	totalProgressWeight: number;
}

/**
 * Compiles a single Blueprint step into a form that can be executed
 *
 * @param playground The PlaygroundClient to use for the compilation
 * @param step The step to compile
 * @param options Additional options for the compilation
 * @returns The compiled step
 */
function compileStep<S extends StepDefinition>(
	step: S,
	{
		semaphore,
		rootProgressTracker,
		totalProgressWeight,
	}: CompileStepArgsOptions
): { run: CompiledStep; step: S; asyncResources: Array<Resource> } {
	const stepProgress = rootProgressTracker.stage(
		(step.progress?.weight || 1) / totalProgressWeight
	);

	const args: any = {};
	for (const key of Object.keys(step)) {
		let value = (step as any)[key];
		if (isFileReference(value)) {
			value = Resource.create(value, {
				semaphore,
			});
		}
		args[key] = value;
	}

	const run = async (playground: UniversalPHP) => {
		try {
			stepProgress.fillSlowly();
			return await stepHandlers[step.step](
				playground,
				await resolveArguments(args),
				{
					tracker: stepProgress,
					initialCaption: step.progress?.caption,
				}
			);
		} finally {
			stepProgress.finish();
		}
	};

	/**
	 * The weight of each async resource is the same, and is the same as the
	 * weight of the step itself.
	 */
	const asyncResources = getResources(args).filter(
		(resource) => resource.isAsync
	);

	const evenWeight = 1 / (asyncResources.length + 1);
	for (const resource of asyncResources) {
		resource.progress = stepProgress.stage(evenWeight);
	}

	return { run, step, asyncResources };
}

/**
 * Gets the resources used by a specific compiled step
 *
 * @param step The compiled step
 * @returns The resources used by the compiled step
 */
function getResources<S extends StepDefinition>(args: S) {
	const result: Resource[] = [];
	for (const argName in args) {
		const resourceMaybe = (args as any)[argName];
		if (resourceMaybe instanceof Resource) {
			result.push(resourceMaybe);
		}
	}
	return result;
}

/**
 * Replaces Resource objects with their resolved values
 *
 * @param step The compiled step
 * @returns The resources used by the compiled step
 */
async function resolveArguments<T extends Record<string, unknown>>(args: T) {
	const resolved: any = {};
	for (const argName in args) {
		const resourceMaybe = (args as any)[argName];
		if (resourceMaybe instanceof Resource) {
			resolved[argName] = await resourceMaybe.resolve();
		} else {
			resolved[argName] = resourceMaybe;
		}
	}
	return resolved;
}

export async function runBlueprintSteps(
	compiledBlueprint: CompiledBlueprint,
	playground: UniversalPHP
) {
	await compiledBlueprint.run(playground);
}
