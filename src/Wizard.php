<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
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
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\SessionInterface;

final class Wizard
{
    public const AUTO_ADVANCE = true;
    public const BRANCH_DISABLED = -1;
    public const BRANCH_ENABLED = 1;
    public const DIRECTION_BACKWARD = -1;
    public const DIRECTION_FORWARD = 1;
    public const DIRECTION_REPEAT = 0;
    public const DEFAULT_BRANCH = true;
    public const EMPTY_STEP = '';
    public const FORWARD_ONLY = true;
    public const INVALID_STEP_EXCEPTION = '"{step}" is not a valid step';
    public const NEXT_STEP_EXCEPTION = 'Step::nextStep cannot be a string if Wizard::autoAdvance is TRUE';
    public const NOT_STARTED_EXCEPTION = 'wizard has not started';
    public const NOT_STARTED_EXCEPTION_INFO = 'Start wizard using step()';
    public const ROUTE_NOT_SET_EXCEPTION = '"{route}" not set';
    public const ROUTE_NOT_SET_EXCEPTION_INFO= 'Set "{route}" using {method} method';
    public const STEPS_NOT_SET_EXCEPTION = '"steps" not set';
    public const STEPS_NOT_SET_EXCEPTION_INFO= 'Set "steps" using withSteps() method';
    public const BRANCH_KEY = 'branch';
    public const DATA_KEY = 'data';
    public const REPETITION_INDEX_KEY = 'repetitionIndex';
    public const SESSION_KEY = 'Wizard';
    public const STEPS_KEY = 'steps';
    public const STEP_TIMEOUT_KEY = 'stepTimeout';
    private const NO_STEP_TIMEOUT = 0;
    public const STEP_PARAMETER = 'step';

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
     * wizard has 5 steps and the user has completed four of them and then goes back to the second step; the expected
     * step is the fifth step, the next step is the third step.
     *
     * If {@link $forwardOnly === TRUE} the expected step is the next step
     */
    private bool $autoAdvance = self::AUTO_ADVANCE;
    private string $completedRoute = '';
    private string $currentStep = '';
    /**
     * @var bool If TRUE the first "non-skipped" branch in a group will be used if a branch has not been specifically selected.
     */
    private bool $defaultBranch = self::DEFAULT_BRANCH;
    private string $expiredRoute = '';
    /**
     * @var bool If TRUE previously completed steps can not be reprocessed.
     */
    private bool $forwardOnly = !self::FORWARD_ONLY;
    private string $stepRoute = '';
    private string $stepParameter = self::STEP_PARAMETER;
    private array $steps = [];
    /**
     * @var int Step stepTimeout in seconds; 0 === no stepTimeout
     */
    private int $stepTimeout = self::NO_STEP_TIMEOUT;
    private string $branchKey = '';
    private string $dataKey = '';
    private string $repetitionIndexKey = '';
    private string $stepsKey = '';
    private string $stepTimeoutKey = '';

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
        private UrlGeneratorInterface $urlGenerator
    )
    {
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function step(string $step, ServerRequestInterface $request): ResponseInterface
    {
        if ($step === self::EMPTY_STEP) {
            if (
                (!$this->hasStarted() && !$this->start())
                || $this->hasCompleted()
            ) {
                $event = new AfterWizard($this);

                $this
                    ->eventDispatcher
                    ->dispatch($event)
                ;

                return $this->createResponse($this->completedRoute);
            }

            return $this
                ->createResponse(
                    $this->stepRoute,
                    [$this->stepParameter => $this->getNextStep()]
                )
            ;
        }

        if (!$this->hasStarted()) {
            throw new RuntimeException(
                self::NOT_STARTED_EXCEPTION,
                self::NOT_STARTED_EXCEPTION_INFO
            );
        }

        if ($this->isValidStep($step)) {
            $this->currentStep = $step;
            $event = new Step($this, $request);

            // The event handler will either render a form or handle data submitted from the form
            $this
                ->eventDispatcher
                ->dispatch($event)
            ;

            if ($request->getMethod() === Method::POST) {
                $this->saveStepData($step, $event->getData());

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

                    return $this
                        ->createResponse(
                            $this->expiredRoute,
                            [$this->stepParameter => $step]
                        )
                    ;
                }

                return $this
                    ->createResponse(
                        $this->stepRoute,
                        [$this->stepParameter => $this->getNextStep($event)]
                    )
                ;
            }

            if ($this->stepTimeout) {
                $this
                    ->session
                    ->set($this->stepTimeoutKey, time() + $this->stepTimeout)
                ;
            }

            return $this->createResponse($this->stepRoute, [$this->stepParameter => $step]);
        }

        throw new InvalidArgumentException(strtr(self::INVALID_STEP_EXCEPTION, ['{step}' => $step]));
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

    public function withCompletedRoute(string $completedRoute): self
    {
        $new = clone $this;
        $new->completedRoute = $completedRoute;
        return $new;
    }

    public function withDefaultBranch(bool $defaultBranch): self
    {
        $new = clone $this;
        $new->defaultBranch = $defaultBranch;
        return $new;
    }

    public function withExpiredRoute(string $expiredRoute): self
    {
        $new = clone $this;
        $new->expiredRoute = $expiredRoute;
        return $new;
    }

    public function withSessionKey(string $sessionKey): self
    {
        $new = clone $this;
        $new->sessionKey = $sessionKey;
        return $new;
    }

    public function withStepParameter(string $stepParameter): self
    {
        $new = clone $this;
        $new->stepParameter = $stepParameter;
        return $new;
    }

    public function withStepRoute(string $stepRoute): self
    {
        $new = clone $this;
        $new->stepRoute = $stepRoute;
        return $new;
    }

    public function withSteps(array $steps): self
    {
        $new = clone $this;
        $new->steps = $steps;
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
        return $this->currentStep;
    }

    /**
     * Reads data stored for a step.
     *
     * @param string $step The name of the step. If empty the data for all steps are returned.
     * @return array Data for the specified step or data for all steps
     */
    public function getData(string $step = self::EMPTY_STEP): array
    {
        $data = $this
            ->session
            ->get($this->dataKey, [])
        ;

        if ($step === self::EMPTY_STEP) {
            return $data;
        }

        return $data[$step] ?? [];
    }

    /**
     * Select, skip, or deselect branch(es)
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
     * @return string The first unprocessed step or an empty string if all steps have been processed
     */
    private function getExpectedStep(): string
    {
        $processedSteps = array_keys($this
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
                return $step;
            }
        }

        return self::EMPTY_STEP;
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
     * If Wizard::forwardOnly === TRUE this results in an invalid step.
     *
     * @param ?Step $event The current step event
     */
    private function getNextStep(?Step $event = null): string
    {
        if ($event === null) { // first step, resumed wizard, or continuing after an invalid step
            if (
                $this->autoAdvance
                && count(
                    $this
                        ->session
                        ->get($this->dataKey)
                )
            ) {
                $nextStep = $this->getExpectedStep();
            } else {
                $steps = $this
                    ->session
                    ->get($this->stepsKey)
                ;
                $nextStep = $steps[0];
            }

            $this
                ->session
                ->set($this->repetitionIndexKey, 0);

            return $nextStep;
        }

        $goto = $event->getGoto();
        $repetitionIndex = $this
            ->session
            ->get($this->repetitionIndexKey)
        ;

        if (is_string($goto)) {
            if ($this->autoAdvance) {
                throw new RuntimeException(self::NEXT_STEP_EXCEPTION);
            }

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
            $steps = array_keys(
                $this
                    ->session
                    ->get($this->stepsKey)
            );
            $index = array_search($event->getStep(), $steps, true) + 1;
            $nextStep = ($index === count(
                $this
                    ->session
                    ->get($this->stepsKey)
            )
                ? self::EMPTY_STEP // wizard has finished
                : $steps[$index]
            );
            $data = $this->session->get($this->dataKey);
            $this
                ->session
                ->set(
                    $this->repetitionIndexKey,
                    $nextStep !== self::EMPTY_STEP && array_key_exists($nextStep, $data)
                        ? count($data[$nextStep])
                        : 0
                )
            ;
        }

        return $nextStep;
    }

    private function hasCompleted(): bool
    {
        return $this->getExpectedStep() === self::EMPTY_STEP;
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

    private function isValidStep(string $step): bool
    {
        if (!$this->hasStarted()) {
            return false;
        }

        $steps = array_keys(
            $this
                ->session
                ->get($this->stepsKey)
        );
        $index = array_search($step, $steps, true);
        $expectedStep = $this->getExpectedStep(); // self::EMPTY_STEP if wizard finished

        return $index === 0
            || ($index >= 0 && ($this->forwardOnly
                    ? $expectedStep !== self::EMPTY_STEP && $index === array_search($expectedStep, $steps, true)
                    : $expectedStep === self::EMPTY_STEP || $index <= array_search($expectedStep, $steps, true)
                ))
            || $expectedStep === self::EMPTY_STEP
            ;
    }

    private function end(): bool
    {
        $event = new AfterWizard($this);
        $this
            ->eventDispatcher
            ->dispatch($event)
        ;

        return false;
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    private function start(): bool
    {
        if ($this->completedRoute === '') {
            throw new InvalidConfigException(
                strtr(self::ROUTE_NOT_SET_EXCEPTION, [
                    '{route}' => 'completedRoute',
                ]),
                strtr(self::ROUTE_NOT_SET_EXCEPTION_INFO, [
                    '{route}' => 'completedRoute',
                    '{method}' => 'withCompletedRoute()',
                ])
            );
        }

        if ($this->stepRoute === '') {
            throw new InvalidConfigException(
                strtr(self::ROUTE_NOT_SET_EXCEPTION, [
                    '{route}' => 'stepRoute',
                ]),
                strtr(self::ROUTE_NOT_SET_EXCEPTION_INFO, [
                    '{route}' => 'stepRoute',
                    '{method}' => 'withStepRoute()',
                ])
            );
        }

        if ($this->stepTimeout > 0 && $this->expiredRoute === '') {
            throw new InvalidConfigException(
                strtr(self::ROUTE_NOT_SET_EXCEPTION, [
                    '{route}' => 'expiredRoute',
                ]),
                strtr(self::ROUTE_NOT_SET_EXCEPTION_INFO, [
                    '{route}' => 'expiredRoute',
                    '{method}' => 'withExpiredRoute()',
                ])
            );
        }

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

        if (!$event->shouldContinue()) {
            return false;
        }

        $this->branchKey = $this->sessionKey . '.' . self::BRANCH_KEY;
        $this->dataKey = $this->sessionKey . '.' . self::DATA_KEY;
        $this->repetitionIndexKey = $this->sessionKey . '.' . self::REPETITION_INDEX_KEY;
        $this->stepsKey = $this->sessionKey . '.' . self::STEPS_KEY;
        $this->stepTimeoutKey = $this->sessionKey . '.' . self::STEP_TIMEOUT_KEY;

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
        $this
            ->session
            ->set($this->stepsKey, $this->parseSteps($this->steps))
        ;

        return true;
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

                        if (is_array($branchSteps)) {
                            foreach ($this->parseSteps($branchSteps) as $branchStep) {
                                $parsed[] = $branchStep;
                            }
                        } else {
                            $parsed[] = $branchSteps;
                        }
                    }
                }
            } else {
                $parsed[] = $step;
            }
        }

        return $parsed;
    }

    private function saveStepData(string $step, array $stepData): void
    {
        $data = $this
            ->session
            ->get($this->dataKey)
        ;

        $repetitionIndex = $this
            ->session
            ->get($this->repetitionIndexKey)
        ;

        if ($repetitionIndex === 0) {
            if (isset($data[$step][0])) {
                $data[$step][0] = $stepData;
            } else {
                $data[$step] = $stepData;
            }
        } elseif ($repetitionIndex === 1) {
            if (!isset($data[$step][0])) {
                $temp = $data[$step];
                unset($data[$step]);
                $data[$step][0] = $temp;
            }

            $data[$step][1] = $stepData;
        } else {
            $data[$step][$repetitionIndex] = $stepData;
        }

        $this->session->set($this->dataKey, $data);
    }

    private function createResponse(string $route, array $parameters = []): ResponseInterface
    {
        return $this
            ->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(
                Header::LOCATION,
                $this
                    ->urlGenerator
                    ->generate($route, $parameters)
            )
        ;
    }
}
