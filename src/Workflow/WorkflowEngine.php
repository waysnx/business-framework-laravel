<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Workflow;

use Ramsey\Uuid\Uuid;
use WaysNX\BusinessFramework\Exceptions\WorkflowExecutionException;
use WaysNX\BusinessFramework\Exceptions\WorkflowStepException;
use WaysNX\BusinessFramework\Lifecycle\LifecycleContext;
use WaysNX\BusinessFramework\Lifecycle\LifecycleEvent;
use WaysNX\BusinessFramework\Lifecycle\LifecycleManager;
use WaysNX\BusinessFramework\Registry\WorkflowDefinition;
use WaysNX\BusinessFramework\Registry\WorkflowRegistry;
use WaysNX\BusinessFramework\Registry\WorkflowStep;
use WaysNX\BusinessFramework\Runtime\BusinessFunctionRuntime;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;

/**
 * WorkflowEngine
 *
 * Executes workflow definitions and coordinates business functions.
 *
 * Purpose:
 * Execute workflow definitions by coordinating business function execution.
 *
 * Responsibilities:
 * - Load workflow definitions from registry
 * - Create and manage execution context
 * - Execute workflow steps in sequence
 * - Coordinate business function execution
 * - Track execution progress
 * - Manage step transitions
 * - Generate execution results
 * - Publish lifecycle events
 * - Generate execution metadata
 *
 * Usage:
 * ```php
 * $engine = new WorkflowEngine(
 *     registry: $workflowRegistry,
 *     lifecycleManager: $lifecycleManager
 * );
 *
 * $context = new WorkflowContext(
 *     workflowId: 'employee-onboarding',
 *     executionId: 'exec-1',
 *     entity: $employee,
 *     inputData: $data
 * );
 *
 * $result = $engine->execute('employee-onboarding', $context);
 * ```
 *
 * Step execution:
 * - Register Business Function instances with registerBusinessFunction();
 *   each step executes its function through BusinessFunctionRuntime.
 * - A step whose function is not registered fails (no silent success).
 * - Steps run in ascending `sequence` order; duplicate step IDs or duplicate
 *   sequences make the run fail before any step executes.
 *
 * Extension Points:
 * - Override beforeExecute() for pre-execution hooks
 * - Override beforeStep() for pre-step hooks
 * - Override afterStep() for post-step hooks
 * - Override beforeComplete() / afterComplete() for completion hooks
 * - Override executeBusinessFunction() to customize request mapping
 * - Override determineNextStep() for custom transitions (the "all steps
 *   executed" completeness check then no longer applies)
 *
 * @package WaysNX\BusinessFramework\Workflow
 */
class WorkflowEngine
{
    /**
     * The workflow registry
     *

     * @var WorkflowRegistry
     */
    private WorkflowRegistry $registry;

    /**
     * The lifecycle manager
     *

     * @var LifecycleManager|null
     */
    private ?LifecycleManager $lifecycleManager;

    /**
     * Current execution state
     *

     * @var array
     */
    private array $executionState = [];

    /**
     * Business Function instances available to workflow steps, keyed by function ID.
     *
     * @var array<string, BusinessFunctionInterface>
     */
    private array $businessFunctions = [];

    /**
     * Optional runtime used to execute step Business Functions.
     */
    private ?BusinessFunctionRuntime $businessFunctionRuntime = null;

    /**
     * Initialize a new WorkflowEngine
     *

     * @param WorkflowRegistry $registry The workflow registry
     * @param LifecycleManager|null $lifecycleManager The lifecycle manager
     */
    public function __construct(
        WorkflowRegistry $registry,
        ?LifecycleManager $lifecycleManager = null
    ) {
        $this->registry = $registry;
        $this->lifecycleManager = $lifecycleManager;
    }

    /**
     * Execute a workflow by ID
     *

     * @param string $workflowId The workflow ID
     * @param WorkflowContext $context The workflow context
     *

     * @return WorkflowResult The workflow result
     *

     * @throws WorkflowExecutionException If execution fails
     */
    public function execute(string $workflowId, WorkflowContext $context): WorkflowResult
    {
        try {
            $workflow = $this->registry->findById($workflowId);
            return $this->executeWorkflow($workflow, $context);
        } catch (\Exception $e) {
            throw new WorkflowExecutionException(
                "Workflow '{$workflowId}' execution failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Execute a workflow definition
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowContext $context The workflow context
     *

     * @return WorkflowResult The workflow result
     *

     * @throws WorkflowExecutionException If execution fails
     */
    public function executeWorkflow(
        WorkflowDefinition $workflow,
        WorkflowContext $context
    ): WorkflowResult {
        $executionId = $context->executionId ?: (string) Uuid::uuid4();
        $startTime = hrtime(true);

        try {
            $this->publishEvent('beforeWorkflow', $workflow->id, $context);
            $this->beforeExecute($workflow, $context);

            // Initialize execution state
            $completedSteps = [];
            $failedSteps = [];
            $errors = [];
            $warnings = [];

            // Phase 0 (LAR-02): reject structurally invalid step definitions
            // (duplicate step IDs, duplicate or negative sequences) before any
            // step executes. Step order is defined only by `sequence`.
            $definitionErrors = $this->validateStepDefinitions($workflow);
            $errors = array_merge($errors, $definitionErrors);

            // Execute steps sequentially
            $currentStepId = empty($definitionErrors) ? $this->getFirstStep($workflow) : null;

            while (!empty($currentStepId)) {
                try {
                    $step = $this->getStepById($workflow, $currentStepId);
                    if ($step === null) {
                        $errors[] = "Step '{$currentStepId}' not found";
                        break;
                    }

                    $this->beforeStep($workflow, $step, $context);

                    // Execute the step
                    $this->executeStep($step, $context);

                    $completedSteps[] = $currentStepId;

                    $this->afterStep($workflow, $step, $context);

                    // Determine next step
                    $currentStepId = $this->determineNextStep($workflow, $step, $completedSteps);
                } catch (\Throwable $e) {
                    $failedSteps[] = $currentStepId;
                    $errors[] = "Step '{$currentStepId}' failed: {$e->getMessage()}";
                    break;
                }
            }

            // Phase 0 (LAR-02): with the default sequence-based transition logic,
            // every defined step is required. A run that did not execute every
            // step must not be reported as completed.
            if (empty($definitionErrors) && empty($failedSteps) && $this->usesDefaultTransitions()) {
                $skipped = array_values(array_diff(
                    array_map(fn(WorkflowStep $s) => $s->id, $workflow->steps),
                    $completedSteps
                ));
                if (!empty($skipped)) {
                    $errors[] = 'Workflow did not execute required steps: ' . implode(', ', $skipped);
                }
            }

            $endTime = hrtime(true);
            $duration = (int) (($endTime - $startTime) / 1_000_000);

            $status = (empty($failedSteps) && empty($errors)) ? 'completed' : 'failed';

            $this->beforeComplete($workflow, $context, $status, $completedSteps, $failedSteps);

            $result = new WorkflowResult(
                executionId: $executionId,
                workflowId: $workflow->id,
                status: $status,
                completedSteps: $completedSteps,
                failedSteps: $failedSteps,
                warnings: $warnings,
                errors: $errors,
                duration: $duration,
                metadata: [
                    'workflowName' => $workflow->name,
                    'stepCount' => count($workflow->steps),
                    'stepsCompleted' => count($completedSteps),
                    'stepsFailed' => count($failedSteps),
                ]
            );

            $this->afterComplete($workflow, $context, $result);

            if ($result->succeeded()) {
                $this->publishEvent('workflowCompleted', $workflow->id, $context);
            } else {
                $this->publishEvent('workflowFailed', $workflow->id, $context);
            }

            $this->publishEvent('afterWorkflow', $workflow->id, $context);

            return $result;
        } catch (\Exception $e) {
            throw new WorkflowExecutionException(
                "Workflow execution failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Execute a single workflow step
     *

     * @param WorkflowStep $step The step to execute
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     *

     * @throws WorkflowStepException If execution fails
     */
    protected function executeStep(WorkflowStep $step, WorkflowContext $context): void
    {
        try {
            $this->publishEvent('beforeStep', $step->id, $context);

            // Execute the business function associated with this step
            $this->executeBusinessFunction($step->businessFunctionId, $context);

            $this->publishEvent('stepCompleted', $step->id, $context);
        } catch (\Throwable $e) {
            $this->publishEvent('stepFailed', $step->id, $context);
            throw new WorkflowStepException(
                "Step '{$step->id}' execution failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Execute a business function (extension point)
     *

     * @param string $functionId The business function ID
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     */
    protected function executeBusinessFunction(string $functionId, WorkflowContext $context): void
    {
        // Phase 0 (LAR-03): the default implementation executes the registered
        // Business Function through the standard BusinessFunctionRuntime pipeline
        // (validation -> authorization -> rules -> execution -> events -> response).
        // Subclasses may still override this method to customize request mapping.
        if (!isset($this->businessFunctions[$functionId])) {
            throw new WorkflowStepException(
                "No business function registered for '{$functionId}'"
            );
        }

        $runtime = $this->businessFunctionRuntime ?? new BusinessFunctionRuntime();

        // Default request mapping: the workflow input data. The caller is
        // host-supplied identity context (metadata key 'caller'); it is passed
        // to the function's authorization step, which makes the decision.
        //
        // run() (not execute()) is used so that the step error recorded in the
        // WorkflowResult carries the PUBLIC message: for an unexpected failure
        // that is the safe message plus reference, never the raw exception
        // message (BF-ERR-03, ADR-0009 amendment). The original exception is
        // kept as the previous exception and reported server-side by run().
        $result = $runtime->run(
            $this->businessFunctions[$functionId],
            $context->inputData,
            $context->getMetadataValue('caller')
        );

        if ($result->failed()) {
            throw new WorkflowStepException(
                "Business function '{$functionId}' failed ({$result->status}): {$result->errorMessage}",
                previous: $result->getException()
            );
        }
    }

    /**
     * Register a Business Function instance for workflow step execution.
     *
     * Steps reference functions by ID (WorkflowStep::$businessFunctionId); the
     * default executeBusinessFunction() resolves the instance registered here.
     *
     * @param BusinessFunctionInterface $function The function instance
     * @return self
     */
    public function registerBusinessFunction(BusinessFunctionInterface $function): self
    {
        $this->businessFunctions[$function->getFunctionId()] = $function;
        return $this;
    }

    /**
     * Check whether a Business Function is registered for step execution.
     */
    public function hasBusinessFunction(string $functionId): bool
    {
        return isset($this->businessFunctions[$functionId]);
    }

    /**
     * Use a specific BusinessFunctionRuntime for step execution
     * (a new runtime per step is used otherwise).
     */
    public function setBusinessFunctionRuntime(BusinessFunctionRuntime $runtime): self
    {
        $this->businessFunctionRuntime = $runtime;
        return $this;
    }

    /**
     * Validate step definitions before execution.
     *
     * Rules (Phase 0 workflow sequencing contract):
     * - step IDs must be unique
     * - sequence values must be unique and non-negative
     *
     * @return array<string> Definition errors (empty when valid)
     */
    protected function validateStepDefinitions(WorkflowDefinition $workflow): array
    {
        $errors = [];
        $ids = [];
        $sequences = [];

        foreach ($workflow->steps as $step) {
            if (isset($ids[$step->id])) {
                $errors[] = "Invalid workflow definition: duplicate step id '{$step->id}'";
            }
            $ids[$step->id] = true;

            if ($step->sequence < 0) {
                $errors[] = "Invalid workflow definition: step '{$step->id}' has negative sequence {$step->sequence}";
            }

            if (isset($sequences[$step->sequence])) {
                $errors[] = "Invalid workflow definition: duplicate sequence {$step->sequence} "
                    . "(steps '{$sequences[$step->sequence]}' and '{$step->id}')";
            } else {
                $sequences[$step->sequence] = $step->id;
            }
        }

        return $errors;
    }

    /**
     * Whether the sequence-based transition logic of this class is in effect
     * (i.e. determineNextStep() is not overridden by a subclass).
     */
    private function usesDefaultTransitions(): bool
    {
        return (new \ReflectionMethod($this, 'determineNextStep'))
            ->getDeclaringClass()->getName() === self::class;
    }

    /**
     * Get the first step in the workflow
     *

     * @param WorkflowDefinition $workflow The workflow definition
     *

     * @return string|null The first step ID or null
     */
    private function getFirstStep(WorkflowDefinition $workflow): ?string
    {
        if (empty($workflow->steps)) {
            return null;
        }

        // Steps are typically ordered by sequence
        $steps = $workflow->steps;
        usort(
            $steps,
            fn($a, $b) => $a->sequence <=> $b->sequence
        );

        return $steps[0]->id ?? null;
    }

    /**
     * Get a step by ID
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param string $stepId The step ID
     *

     * @return WorkflowStep|null The step or null
     */
    private function getStepById(WorkflowDefinition $workflow, string $stepId): ?WorkflowStep
    {
        foreach ($workflow->steps as $step) {
            if ($step->id === $stepId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Determine the next step (extension point)
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowStep $currentStep The current step
     * @param array<string> $completedSteps Completed step IDs
     *

     * @return string|null The next step ID or null
     */
    protected function determineNextStep(
        WorkflowDefinition $workflow,
        WorkflowStep $currentStep,
        array $completedSteps
    ): ?string {
        // Phase 0 (LAR-02): the next step is the step with the smallest
        // sequence strictly greater than the current one. Declaration (array)
        // order is irrelevant.
        $next = null;
        foreach ($workflow->steps as $step) {
            if (
                $step->sequence > $currentStep->sequence
                && ($next === null || $step->sequence < $next->sequence)
            ) {
                $next = $step;
            }
        }

        return $next?->id;
    }

    /**
     * Publish a lifecycle event
     *

     * @param string $eventName The event name
     * @param string $workflowId The workflow ID
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     */
    private function publishEvent(string $eventName, string $workflowId, WorkflowContext $context): void
    {
        if ($this->lifecycleManager === null) {
            return;
        }

        $event = new LifecycleEvent($eventName, [
            'workflowId' => $workflowId,
        ]);

        $lifecycleContext = new LifecycleContext(
            entityId: $context->executionId,
            entityType: 'Workflow',
            moduleId: $context->moduleId,
            businessFunctionId: $context->businessFunctionId,
            correlationId: $context->correlationId,
            metadata: $context->metadata
        );

        $this->lifecycleManager->dispatch($event, $lifecycleContext);
    }

    /**
     * Extension point: before workflow execution
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     */
    protected function beforeExecute(WorkflowDefinition $workflow, WorkflowContext $context): void
    {
        // Override in subclasses
    }

    /**
     * Extension point: before step execution
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowStep $step The step being executed
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     */
    protected function beforeStep(
        WorkflowDefinition $workflow,
        WorkflowStep $step,
        WorkflowContext $context
    ): void {
        // Override in subclasses
    }

    /**
     * Extension point: after step execution
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowStep $step The step that was executed
     * @param WorkflowContext $context The workflow context
     *

     * @return void
     */
    protected function afterStep(
        WorkflowDefinition $workflow,
        WorkflowStep $step,
        WorkflowContext $context
    ): void {
        // Override in subclasses
    }

    /**
     * Extension point: before result completion
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowContext $context The workflow context
     * @param string $status The workflow status
     * @param array<string> $completedSteps Completed step IDs
     * @param array<string> $failedSteps Failed step IDs
     *

     * @return void
     */
    protected function beforeComplete(
        WorkflowDefinition $workflow,
        WorkflowContext $context,
        string $status,
        array $completedSteps,
        array $failedSteps
    ): void {
        // Override in subclasses
    }

    /**
     * Extension point: after result completion
     *

     * @param WorkflowDefinition $workflow The workflow definition
     * @param WorkflowContext $context The workflow context
     * @param WorkflowResult $result The workflow result
     *

     * @return void
     */
    protected function afterComplete(
        WorkflowDefinition $workflow,
        WorkflowContext $context,
        WorkflowResult $result
    ): void {
        // Override in subclasses
    }

    /**
     * Set lifecycle manager
     *

     * @param LifecycleManager $lifecycleManager The lifecycle manager
     *

     * @return self Returns self for method chaining
     */
    public function setLifecycleManager(LifecycleManager $lifecycleManager): self
    {
        $this->lifecycleManager = $lifecycleManager;
        return $this;
    }

    /**
     * Get lifecycle manager
     *

     * @return LifecycleManager|null The lifecycle manager or null
     */
    public function getLifecycleManager(): ?LifecycleManager
    {
        return $this->lifecycleManager;
    }

    /**
     * Get workflow registry
     *

     * @return WorkflowRegistry The workflow registry
     */
    public function getRegistry(): WorkflowRegistry
    {
        return $this->registry;
    }
}
