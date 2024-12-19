<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard;

use BeastBytes\Wizard\Event\AfterWizard;
use BeastBytes\Wizard\Event\BeforeWizard;
use BeastBytes\Wizard\Event\Step;
use BeastBytes\Wizard\Event\StepExpired;
use BeastBytes\Wizard\Exception\InvalidConfigException;
use BeastBytes\Wizard\Exception\RuntimeException;
use Exception;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Strings\Inflector;

final class Wizard implements WizardInterface
{
    public const AUTO_ADVANCE = true;
    public const BRANCH_DISABLED = -1;
    public const BRANCH_ENABLED = 1;
    public const DIRECTION_BACKWARD = -1;
    public const DIRECTION_FORWARD = 1;
    public const DIRECTION_REPEAT = 0;
    public const DEFAULT_BRANCH = true;
    public const FORWARD_ONLY = true;
    public const STEP_PARAMETER = 'step';
    private const EVENT_NOT_SET_EXCEPTION = 'Step event not set';
    private const EVENT_NOT_SET_EXCEPTION_INFO = 'Set the event using withStepEvent() method. The parameter is the FQCN of the event which must extend BeastBytes\Wizard\Event\Step';
    private const INVALID_STEP_EVENT = 'Invalid step event class';
    private const INVALID_STEP_EVENT_INFO = 'The step event class must extend BeastBytes\Wizard\Event\Step';
    private const STEPS_NOT_SET_EXCEPTION = '"steps" not set';
    private const STEPS_NOT_SET_EXCEPTION_INFO= 'Set "steps" using withSteps() method';
    private const BRANCH_KEY = 'branch';
    private const CURRENT_STEP_KEY = 'currentStep';
    private const DATA_KEY = 'data';
    private const REPETITION_INDEX_KEY = 'repetitionIndex';
    private const SESSION_KEY = '__wizard';
    private const STEPS_KEY = 'steps';
    private const STEP_TIMEOUT_KEY = 'stepTimeout';
    private const NO_STEP_TIMEOUT = 0;

    /**
     * @var string The session key to hold wizard information
     */
    private string $sessionKey = self::SESSION_KEY;

    /**
     * @var bool
     *
     * If TRUE, the behavior will redirect to the "expected step" after a step has been successfully completed.
     * If FALSE, it will redirect to the next step in the steps array.
     *
     * The difference between the "expected step" and the "next step" is when the user goes to a previous step in the
     * wizard; the expected step is the first unprocessed step, the next step is the next step. For example, if the
     * wizard has 5 steps and the user has completed four of them then goes back to the second step; the expected
     * step is the fifth step, the next step is the third step.
     *
     * If {@link $forwardOnly === TRUE} the expected step is the next step
     */
    private bool $autoAdvance = self::AUTO_ADVANCE;
    /**
     * @var bool If TRUE the first "non-skipped" branch in a group will be used if a branch has not been specifically selected.
     */
    private bool $defaultBranch = self::DEFAULT_BRANCH;
    /**
     * @var bool If TRUE previously completed steps can not be reprocessed.
     */
    private bool $forwardOnly = !self::FORWARD_ONLY;
    private string $id = '';
    /**
     * Used during pause() and resume()
     */
    private array $sessionData = [];
    private array $steps = [];
    private string $stepParameter = '';
    /**
     * @var int Step timeout in seconds
     */
    private int $stepTimeout = self::NO_STEP_TIMEOUT;
    private string $branchKey = '';
    private string $currentStepKey = '';
    private string $dataKey = '';
    private string $repetitionIndexKey = '';
    private string $stepsKey = '';
    private string $stepTimeoutKey = '';

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private Inflector $inflector,
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
        private UrlGeneratorInterface $urlGenerator
    )
    {
        $this->setKeyNames();
    }

    /**
     * @throws InvalidConfigException
     */
    public function step(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!$this->hasStarted()) {
            if ($this->start()) {
                return $this // ensure the first step shows the step parameter if it is used
                ->responseFactory
                    ->createResponse(Status::FOUND)
                    ->withHeader(
                        Header::LOCATION,
                        $this
                            ->urlGenerator
                            ->generateFromCurrent($this->getStepParameter())
                    )
                ;
            } else {
                return $this->end();
            }
        }

        $event = new Step($this, $request);

        // The event handler will either render a form or handle data submitted from the form
        $this
            ->eventDispatcher
            ->dispatch($event)
        ;

        if ($event->hasData()) { // it won't if the form is to be rendered
            $this->saveStepData($event);

            if ($event->isWizardStopped()) {
                return $this->end();
            }

            $branches = $event->getBranches();
            if (!empty($branches)) {
                $this->branch($branches);
            }

            if ($this->hasStepExpired()) {
                $event = new StepExpired($this);

                $this
                    ->eventDispatcher
                    ->dispatch($event)
                ;

                return $event->getResponse();
            }

            $nextStep = $this->getNextStep($event);

            if ($nextStep === null) {
                return $this->end();
            }

            $this->setCurrentStep($nextStep);

            return $this
                ->responseFactory
                ->createResponse(Status::FOUND)
                ->withHeader(
                    Header::LOCATION,
                    $this
                        ->urlGenerator
                        ->generateFromCurrent($this->getStepParameter())
                )
            ;
        }

        if ($event->isWizardStopped()) {
            return $this->end();
        }

        if ($this->stepTimeout) {
            $this
                ->session
                ->set(
                    $this->stepTimeoutKey,
                    time() + $this->stepTimeout
                )
            ;
        }

        return $event->getResponse();
    }

    public function withAutoAdvance(bool $autoAdvance): self
    {
        $new = clone $this;
        $new->autoAdvance = $autoAdvance;
        return $new;
    }

    public function withForwardOnly(bool $forwardOnly): self
    {
        $new = clone $this;
        $new->forwardOnly = $forwardOnly;
        return $new;
    }

    public function withDefaultBranch(bool $defaultBranch): self
    {
        $new = clone $this;
        $new->defaultBranch = $defaultBranch;
        return $new;
    }

    public function withId(string $id): self
    {
        $new = clone $this;
        $new->id = $id;
        return $new;
    }

    public function withSessionKey(string $sessionKey): self
    {
        $new = clone $this;
        $new->sessionKey = $sessionKey;
        $new->setKeyNames();
        return $new;
    }

    public function withSteps(array $steps): self
    {
        $new = clone $this;
        $new->steps = $steps;
        return $new;
    }

    public function withStepParameter(string $stepParameter): self
    {
        $new = clone $this;
        $new->stepParameter = $stepParameter;
        return $new;
    }

    public function withStepTimeout(int $stepTimeout): self
    {
        $new = clone $this;
        $new->stepTimeout = $stepTimeout;
        return $new;
    }

    public function reset(): void
    {
        foreach ([
                     $this->branchKey,
                     $this->dataKey,
                     $this->repetitionIndexKey,
                     $this->stepsKey,
                     $this->stepTimeoutKey,
                 ] as $key) {
            $this
                ->session
                ->remove($key)
            ;
        }
    }

    public function getCurrentStep(): string
    {
        return (string) $this
            ->session
            ->get(
                $this->currentStepKey,
                ''
            )
        ;
    }

    /**
     * Reads data stored for all steps or a single step.
     *
     * @param ?string $step The name of the step whose data to return or null to return data for all steps
     * @return mixed Data for the specified step or data for all steps
     */
    public function getData(?string $step = null): mixed
    {
        $data = (array) $this
            ->session
            ->get($this->dataKey, [])
        ;

        if ($step === null) {
            return $data;
        }

        return $data[$step] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array The active steps. These may change when using PBN
     */
    public function getSteps(): array
    {
        return $this->hasStarted()
            ? (array) $this
                ->session
                ->get($this->stepsKey)
            : []
            ;
    }

    /**
     * @throws Exception
     */
    public function pause(): string
    {
        foreach ([
                     $this->branchKey,
                     $this->currentStepKey,
                     $this->dataKey,
                     $this->repetitionIndexKey,
                     $this->stepsKey,
                 ] as $sessionKey) {
            $this->sessionData[$sessionKey] = $this
                ->session
                ->get($sessionKey)
            ;
        }

        if (
            $this
                ->session
                ->has($this->stepTimeoutKey)
        ) {
            $this->sessionData[$this->stepTimeoutKey] = $this
                ->session
                ->get($this->stepTimeoutKey)
            ;
        }

        $this->reset();

        return serialize([
            $this->sessionKey,
            $this->autoAdvance,
            $this->defaultBranch,
            $this->forwardOnly,
            $this->sessionData,
            $this->steps,
            $this->stepTimeout,
            $this->branchKey,
            $this->dataKey,
            $this->repetitionIndexKey,
            $this->stepsKey,
            $this->stepTimeoutKey,
        ]);
    }

    public function resume(string $data): void
    {
        [
            $this->sessionKey,
            $this->autoAdvance,
            $this->defaultBranch,
            $this->forwardOnly,
            $this->sessionData,
            $this->steps,
            $this->stepTimeout,
            $this->branchKey,
            $this->dataKey,
            $this->repetitionIndexKey,
            $this->stepsKey,
            $this->stepTimeoutKey,
        ] = unserialize($data, ['allowed_classes' => false]);

        foreach ($this->sessionData as $key => $value) {
            $this
                ->session
                ->set($key, $value)
            ;
        }

        $this->sessionData = [];
    }

    /**
     * Enable or disable branch(es).
     *
     * @param non-empty-array<string, int> $directives Branches as ["branch name" => branchDirective]
     * [key => value]  pairs.
     * branchDirective = Wizard::BRANCH_DISABLED | Wizard::BRANCH_ENABLED
     */
    private function branch(array $directives): void
    {
        $branches = $this
            ->session
            ->get($this->branchKey)
        ;

        foreach ($directives as $name => $directive) {
            ArrayHelper::setValue($branches, $name, $directive);
        }

        $this
            ->session
            ->set($this->branchKey, $branches)
        ;

        $this
            ->session
            ->set($this->stepsKey, $this->parseSteps($this->steps))
        ;
    }

    /**
     * Returns the first unprocessed step (i.e. step data not saved in Session).
     *
     * @return ?string The first unprocessed step or null if all steps have been processed
     */
    private function getExpectedStep(): ?string
    {
        $processedSteps = array_keys((array) $this
            ->session
            ->get($this->dataKey)
        );

        foreach (
            $this
                ->session
                ->get($this->stepsKey)
            as $step
        ) {
            if (!in_array($step, $processedSteps, true)) {
                return (string) $step;
            }
        }

        return null;
    }

    /**
     * Moves the wizard to the next step.
     * The next step is determined by Step::goto, valid values
     * are:
     * - Wizard::DIRECTION_FORWARD (default) - moves to the next step.
     * If autoAdvance == TRUE this will be the expectedStep,
     * if autoAdvance == FALSE this will be the next step in the steps array
     * - Wizard::DIRECTION_BACKWARD - moves to the previous step (which may be an earlier repeated step).
     * If Wizard::forwardOnly === TRUE this results in an invalid step
     *
     * If a string it is the name of the step to return to. This allows multiple steps to be repeated.
     *
     * @param ?Step $event The current step event
     */
    private function getNextStep(?Step $event = null): ?string
    {
        if ($event === null) { // first step or resumed wizard
            if (count($this->getData()) && $this->autoAdvance) {
                $nextStep = $this->getExpectedStep();
            } else {
                /** @var array $steps */
                $steps = $this
                    ->session
                    ->get($this->stepsKey)
                ;
                $nextStep = (string) $steps[0];
            }

            $this
                ->session
                ->set($this->repetitionIndexKey, 0);

            return $nextStep;
        }

        $currentStep = $this->getCurrentStep();
        if (empty($this->getData($currentStep))) { // data not written due to form error
            return $currentStep;
        }

        $goto = $event->getGoto();
        /** @var int $repetitionIndex */
        $repetitionIndex = $this
            ->session
            ->get($this->repetitionIndexKey)
        ;

        if (
            is_string($goto)
            && !$this->forwardOnly
            && $this->isValidStep($goto)
        ) {
            $this
                ->session
                ->set(
                    $this->repetitionIndexKey,
                    count(
                        ArrayHelper::getValue(
                            $this
                                ->session
                                ->get($this->dataKey),
                            $goto
                        )
                    )
                )
            ;

            return $goto;
        }

        if ($goto === self::DIRECTION_BACKWARD && !$this->forwardOnly) {
            if ($repetitionIndex === 0) { // go to the previous step
                /** @var array $steps */
                $steps = $this
                    ->session
                    ->get($this->stepsKey)
                ;
                $index = array_search($this->getCurrentStep(), $steps, true);
                $nextStep = $steps[($index === 0 ? 0 : $index - 1)];

                $this
                    ->session
                    ->set(
                        $this->repetitionIndexKey,
                        count(
                            ArrayHelper::getValue(
                                $this
                                    ->session
                                    ->get($this->dataKey),
                                $nextStep
                            )
                        ) - 1
                    )
                ;

                return $nextStep;
            }

            // go to the previous step in a set of repeated steps
            $this
                ->session
                ->set(
                    $this->repetitionIndexKey,
                    $repetitionIndex - 1
                )
            ;

            return $this->getCurrentStep();
        }

        if ($goto === self::DIRECTION_REPEAT) {
            $this
                ->session
                ->set(
                    $this->repetitionIndexKey,
                    $repetitionIndex + 1
                )
            ;

            return $this->getCurrentStep();
        }

        if ($this->autoAdvance) {
            $nextStep = $this->getExpectedStep();
            $this
                ->session
                ->set($this->repetitionIndexKey, 0)
            ;
        } else {
            /** @var array $steps */
            $steps = $this
                ->session
                ->get($this->stepsKey)
            ;
            $index = array_search($this->getCurrentStep(), $steps, true) + 1;
            $nextStep = ($index === count($this->getSteps())
                ? null // wizard has finished
                : $steps[$index]
            );

            /** @var array $data */
            $data = $this
                ->session
                ->get($this->dataKey)
            ;
            $this
                ->session
                ->set(
                    $this->repetitionIndexKey,
                    $nextStep !== null && array_key_exists($nextStep, $data)
                        ? count($data[$nextStep])
                        : 0
                )
            ;
        }

        return $nextStep;
    }

    private function getStepParameter(): array
    {
        if ($this->stepParameter) {
            /** @var int $repetitionIndex */
            $repetitionIndex = $this
                ->session
                ->get($this->repetitionIndexKey)
            ;

            return [
                $this->stepParameter => $this
                        ->inflector
                        ->toSnakeCase($this->getCurrentStep())
                    . ($repetitionIndex > 0
                        ? '_' . (string) ($repetitionIndex + 1)
                        : ''
                    )
            ];
        }

        return [];
    }

    private function isValidStep(string $step): bool
    {
        /** @var array $steps */
        $steps = $this
            ->session
            ->get($this->stepsKey)
        ;

        if (in_array($step, $steps, true)) {
            return (
                array_search($step, $steps, true)
                <= array_search($this->getExpectedStep(), $steps, true)
            );
        }

        return $this->getExpectedStep() === null;
    }

    private function hasStarted(): bool
    {
        return $this
            ->session
            ->has($this->dataKey)
        ;
    }

    private function hasStepExpired(): bool
    {
        return $this
                ->session
                ->has($this->stepTimeoutKey)
            && $this
                ->session
                ->get($this->stepTimeoutKey) < time()
            ;
    }

    private function setCurrentStep(string $currentStep): void
    {
        $this
            ->session
            ->set($this->currentStepKey, $currentStep)
        ;
    }

    private function setKeyNames(): void
    {
        $this->branchKey = $this->sessionKey . '.' . self::BRANCH_KEY;
        $this->currentStepKey = $this->sessionKey . '.' . self::CURRENT_STEP_KEY;
        $this->dataKey = $this->sessionKey . '.' . self::DATA_KEY;
        $this->repetitionIndexKey = $this->sessionKey . '.' . self::REPETITION_INDEX_KEY;
        $this->stepsKey = $this->sessionKey . '.' . self::STEPS_KEY;
        $this->stepTimeoutKey = $this->sessionKey . '.' . self::STEP_TIMEOUT_KEY;
    }

    /**
     * @throws InvalidConfigException
     */
    private function start(): bool
    {
        if ($this->steps === []) {
            throw new InvalidConfigException(
                self::STEPS_NOT_SET_EXCEPTION,
                self::STEPS_NOT_SET_EXCEPTION_INFO
            );
        }

        $event = new BeforeWizard($this);
        $this
            ->eventDispatcher
            ->dispatch($event)
        ;

        if ($event->isWizardStopped()) {
            return false;
        }

        $this
            ->session
            ->set($this->branchKey, [])
        ;
        $this
            ->session
            ->set($this->dataKey, [])
        ;
        $this
            ->session
            ->set($this->repetitionIndexKey, 0)
        ;

        if ($this->stepTimeout !== self::NO_STEP_TIMEOUT) {
            $this
                ->session
                ->set(
                    $this->stepTimeoutKey,
                    $this->stepTimeout
                )
            ;
        }
        $this
            ->session
            ->set(
                $this->stepsKey,
                $this->parseSteps($this->steps)
            )
        ;
        $this
            ->session
            ->set(
                $this->currentStepKey,
                $this->getNextStep()
            );

        return true;
    }

    private function end(): ?ResponseInterface
    {
        $event = new AfterWizard($this);
        $this
            ->eventDispatcher
            ->dispatch($event)
        ;

        $this->reset();

        return $event->getResponse();
    }

    private function parseSteps(array $steps): array
    {
        $parsed = [];

        foreach ($steps as $step) {
            if (is_array($step)) {
                $defaultBranchEnabled = false;

                foreach ($step as $branchName => $branchSteps) {
                    $branchDirective = ArrayHelper::getValue(
                        $this
                            ->session
                            ->get($this->branchKey),
                        $branchName,
                        null
                    );

                    if (
                        $branchDirective === self::BRANCH_ENABLED
                        || ($branchDirective === null && $defaultBranchEnabled === false && $this->defaultBranch)
                    ) {
                        $defaultBranchEnabled = true;
                        array_push($parsed, ...$branchSteps);
                    }
                }
            } else {
                $parsed[] = $step;
            }
        }

        return $parsed;
    }

    private function saveStepData(Step $event): void
    {
        /** @var array $data */
        $data = $this
            ->session
            ->get($this->dataKey)
        ;

        /** @var int $repetitionIndex */
        $repetitionIndex = $this
            ->session
            ->get($this->repetitionIndexKey)
        ;

        if ($repetitionIndex === 0) {
            if (isset($data[$this->getCurrentStep()][0])) { // repeat of first in repeated steps
                $data[$this->getCurrentStep()][0] = $event->getData();
            } else { // non-repeating step
                $data[$this->getCurrentStep()] = $event->getData();
            }
        } elseif ($repetitionIndex === 1) {
            if (!isset($data[$this->getCurrentStep()][0])) {
                $temp = $data[$this->getCurrentStep()];
                unset($data[$this->getCurrentStep()]);
                $data[$this->getCurrentStep()][0] = $temp;
            }

            $data[$this->getCurrentStep()][1] = $event->getData();
        } else {
            $data[$this->getCurrentStep()][$repetitionIndex] = $event->getData();
        }

        $this
            ->session
            ->set($this->dataKey, $data)
        ;
    }
}