<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Tests;

use BeastBytes\Wizard\Event\AfterWizard;
use BeastBytes\Wizard\Event\BeforeWizard;
use BeastBytes\Wizard\Event\Step;
use BeastBytes\Wizard\Event\StepExpired;
use BeastBytes\Wizard\Exception\InvalidConfigException;
use BeastBytes\Wizard\Exception\RuntimeException;
use BeastBytes\Wizard\Wizard;
use Generator;
use HttpSoft\Message\Response;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\UriFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Session;
use Yiisoft\Strings\Inflector;

class WizardTest extends TestCase
{
    private const REPETITIONS = [
        'repetition_1',
        'repetition_2',
        'repetition_3',
        'repetition_4',
    ];
    private const AUTO_ADVANCE_STEP = 'auto_advance_step';
    private const BACK_STEP = 'back_step';
    private const BRANCH_STEP = 'branch_step';
    private const END_STEP = 'end_step';
    private const GOTO_STEP = 'goto_step';
    private const NON_EXISTENT_STEP = 'non_existent_step';
    private const PAUSE_STEP = 'pause_step';
    private const REPEAT_STEP = 'repeat_step';
    private const RETURN_TO_STEP = 'return_to_step';
    private const TOO_FAR_STEP = 'too_far';
    private const STEP_PARAMETER = 'step';
    private const STEP_ROUTE = 'stepRoute';
    private const TIMEOUT_STEP = 'timeout_step';
    private const STEP_TIMEOUT = 2;
    private const URI = '/wizard';

    private array $branches = [];
    private array $data = [];
    private bool $endOnGet;
    private string $goto;
    private bool $repeatGoBack;
    private int $repeatGoBackIndex;
    private array $events;
    private int $index;
    private static Session $session;
    private Wizard $wizard;

    public static function setUpBeforeClass(): void
    {
        self::$session = new Session();
    }

    protected function setUp(): void
    {
        $this->events = [
            AfterWizard::class => 0,
            BeforeWizard::class => 0,
            Step::class => 0,
            StepExpired::class => 0,
        ];

        $this->index = 0;
        $this->endOnGet = false;
        $this->repeatGoBack = false;

        $this->wizard = new Wizard(
            new Inflector(),
            new ResponseFactory(),
            self::$session,
            $this->createUrlGenerator()
        );
        $this->wizard->reset();
    }

    protected function tearDown(): void
    {
        $this
            ->wizard
            ->reset()
        ;
    }

    #[Test]
    #[DataProvider('sessionKeyProvider')]
    public function session_key(string $sessionKey): void
    {
        if ($sessionKey === '') {
            $sessionKey = '__wizard';
        } else {
            $this->wizard = $this
                ->wizard
                ->withSessionKey($sessionKey)
            ;
        }

        $reflectionClass = new ReflectionClass($this->wizard);
        $reflectionProperty = $reflectionClass->getProperty('sessionKey');
        $reflectionProperty->setAccessible(true);

        $this->assertSame($sessionKey, $reflectionProperty->getValue($this->wizard));
    }

    #[Test]
    public function no_steps(): void
    {
        try {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (InvalidConfigException $exception) {
            $this->assertSame('"steps" not set', $exception->getMessage());
            $this->assertSame('Set "steps" using the withSteps() method', $exception->getSolution());
        }
    }

    #[Test]
    public function no_events(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        try {
            $this
                ->wizard
                ->withSteps($steps)
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (InvalidConfigException $exception) {
            $this->assertSame('"events" not set', $exception->getMessage());
            $this->assertSame(
                'Set "events" using the withEvents() method; the AfterWizard and Step events must be set, the StepExpired event must be set if the Wizard has a stepTimeout, the BeforeWizard event is optional',
                $exception->getSolution()
            );
        }
    }

    #[Test]
    public function no_step_event(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        try {
            $this
                ->wizard
                ->withSteps($steps)
                ->withEvents([])
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (InvalidConfigException $exception) {
            $this->assertSame('Step event not set', $exception->getMessage());
            $this->assertSame('Set Step event using the withEvents() method', $exception->getSolution());
        }
    }

    #[Test]
    public function no_after_wizard_event(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        try {
            $this
                ->wizard
                ->withSteps($steps)
                ->withEvents([
                    Step::class => [$this, 'step'],
                ])
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (InvalidConfigException $exception) {
            $this->assertSame('AfterWizard event not set', $exception->getMessage());
            $this->assertSame('Set AfterWizard event using the withEvents() method', $exception->getSolution());
        }
    }

    #[Test]
    public function no_step_expired_event(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        try {
            $this
                ->wizard
                ->withStepTimeout(2)
                ->withEvents([
                    Step::class => [$this, 'step'],
                    AfterWizard::class => [$this, 'afterWizard']
                ])
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (InvalidConfigException $exception) {
            $this->assertSame('StepExpired event not set', $exception->getMessage());
            $this->assertSame('Set StepExpired event using the withEvents() method', $exception->getSolution());
        }
    }

    #[Test]
    public function step_timeout_after_events(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        try {
            $this
                ->wizard
                ->withSteps($steps)
                ->withEvents([
                    Step::class => [$this, 'step'],
                    AfterWizard::class => [$this, 'afterWizard']
                ])
                ->withStepTimeout(2)
                ->step(new ServerRequest(method: Method::GET))
            ;
        } catch (RuntimeException $exception) {
            $this->assertSame('withStepTimeout() can not be used after withEvents()', $exception->getMessage());
            $this->assertSame('Call withStepTimeout() before withEvents()', $exception->getSolution());
        }
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function start_wizard(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        $result = $this
            ->wizard
            ->withSteps($steps)
            ->withEvents([
                AfterWizard::class => [$this, 'afterWizard'],
                BeforeWizard::class => [$this, 'beforeWizard'],
                Step::class => [$this, 'step'],
            ])
            ->step(new ServerRequest(method: Method::GET))
        ;

        $this->assertSame(1, $this->events[BeforeWizard::class]);
        $this->assertSame(
            $steps,
            self::$session->get('__wizard.steps')
        );
        $this->assertSame(Status::FOUND, $result->getStatusCode());
        $this->assertEmpty($this->wizard->getData());
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('autoAdvanceProvider')]
    public function steps(bool $autoAdvance): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        $expectedData = [];
        foreach ($steps as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->wizard = $this
            ->wizard
            ->withAutoAdvance($autoAdvance)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        $count = 0;
        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;

            $this->assertSame(
                $steps[$count],
                $this
                    ->wizard
                    ->getCurrentStep()
            );

            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;

            $count++;
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function end_on_get(): void
    {
        $steps = ['step_1', 'step_2', 'step_3', self::END_STEP, 'step_4', 'step_6'];
        $expectedData = [];

        $this->endOnGet = true;

        $this->wizard = $this
            ->wizard
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;

        $index = array_search(self::END_STEP, $steps, true) + 1;
        for ($i = 0; $i < $index; $i++) {
            if ($i < $index - 1) {
                $expectedData[$steps[$i]] = ['key' => $steps[$i] . '-value'];
            }
        }

        do {
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;

            if ($this->events[AfterWizard::class] === 0) {
                $result = $this
                    ->wizard
                    ->step(new ServerRequest(method: Method::POST))
                ;
            }
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function end_on_post(): void
    {
        $steps = ['step_1', 'step_2', 'step_3', self::END_STEP, 'step_4', 'step_6'];
        $expectedData = [];

        $this->wizard = $this
            ->wizard
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;

        $index = array_search(self::END_STEP, $steps, true) + 1;
        for ($i = 0; $i < $index; $i++) {
            $expectedData[$steps[$i]] = ['key' => $steps[$i] . '-value'];
        }

        do {
            $this
                ->wizard
                ->withEvents([
                    Step::class => [$this, 'step'],
                    AfterWizard::class => [$this, 'afterWizard']
                ])
                ->step(new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('forwardOnlyProvider')]
    public function direction_backward(bool $forwardOnly): void
    {
        $steps = ['step_1', 'step_2', self::BACK_STEP, 'step_4'];
        $this->wizard = $this
            ->wizard
            ->withForwardOnly($forwardOnly)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while (
            $this
                ->wizard
                ->getCurrentStep() !== self::BACK_STEP
        );

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::POST))
        ;

        $index = array_search(self::BACK_STEP, $steps);
        if ($forwardOnly) {
            $index++;
        } else {
            $index--;
        }

        $this->assertSame(
            $steps[$index],
            $this
                ->wizard
                ->getCurrentStep()
        );
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('autoAdvanceProvider')]
    public function auto_advance(bool $autoAdvance): void
    {
        $steps = ['step_1', self::RETURN_TO_STEP, 'step_3', 'step_4', self::AUTO_ADVANCE_STEP, 'step_6'];
        $this->wizard = $this
            ->wizard
            ->withAutoAdvance($autoAdvance)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while (
            $this
                ->wizard
                ->getCurrentStep()
            !== self::AUTO_ADVANCE_STEP
        );

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::POST))
        ;

        $this->assertSame(
            self::RETURN_TO_STEP,
            $this
                ->wizard
                ->getCurrentStep()
        );

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::POST))
        ;

        if ($autoAdvance) {
            $index = array_search(self::AUTO_ADVANCE_STEP, $steps) + 1;
        } else {
            $index = array_search(self::RETURN_TO_STEP, $steps) + 1;
        }

        $this->assertSame(
            $steps[$index],
            $this
                ->wizard
                ->getCurrentStep()
        );
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('branchProvider')]
    public function branching(array $steps, bool $defaultBranch, array $branches, array $path): void
    {
        $expectedData = [];

        $this->branches = $branches;
        $this->wizard = $this
            ->wizard
            ->withDefaultBranch($defaultBranch)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        foreach ($path as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];

            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;

            $this->assertSame(
                $step,
                $this
                    ->wizard
                    ->getCurrentStep()
            );

            $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        }

        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function repeat_step(): void
    {
        $steps = ['step_1', self::REPEAT_STEP, 'step_3'];
        $expectedData = [
            'step_1' => ['key' => 'step_1-value'],
            self::REPEAT_STEP => [
                ['key-0' => self::REPEAT_STEP . '-value-0'],
                ['key-1' => self::REPEAT_STEP . '-value-1'],
                ['key-2' => self::REPEAT_STEP . '-value-2'],
                ['key-3' => self::REPEAT_STEP . '-value-3'],
            ],
            'step_3' => ['key' => 'step_3-value'],
        ];

        $this->wizard = $this
            ->wizard
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('repeatGoBackIndexProvider')]
    public function direction_backward_in_repeated_steps(int $repeatGoBackIndex): void
    {
        $this->repeatGoBack = true;
        $this->repeatGoBackIndex = $repeatGoBackIndex;
        $steps = ['step_1', self::REPEAT_STEP, 'step_3'];
        $expectedData = [
            'step_1' => ['key' => 'step_1-value'],
            self::REPEAT_STEP => [
                ['key-0' => self::REPEAT_STEP . '-value-0'],
                ['key-1' => self::REPEAT_STEP . '-value-1'],
                ['key-2' => self::REPEAT_STEP . '-value-2'],
                ['key-3' => self::REPEAT_STEP . '-value-3'],
            ],
            'step_3' => ['key' => 'step_3-value'],
        ];

        $this->wizard = $this
            ->wizard
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    #[DataProvider('gotoProvider')]
    public function goto_step(string $goto, bool $autoAdvance, bool $forwardOnly): void
    {
        $steps = [
            'step_1',
            self::RETURN_TO_STEP,
            'step_3',
            self::GOTO_STEP,
            'step_5',
            'step_6',
            self::TOO_FAR_STEP,
            'step_8'
        ];

        $this->goto = $goto;

        $this->wizard = $this
            ->wizard
            ->withAutoAdvance($autoAdvance)
            ->withForwardOnly($forwardOnly)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while (
            $this
                ->wizard
                ->getCurrentStep()
            !== self::GOTO_STEP
        );

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::POST))
        ;

        $index = array_search(self::GOTO_STEP, $steps);
        if (
            $forwardOnly
            || $goto === self::NON_EXISTENT_STEP
            || $goto === self::TOO_FAR_STEP
        ) {
            $this->assertSame(
                $steps[$index + 1],
                $this
                    ->wizard
                    ->getCurrentStep()
            );
        } else {
            $this->assertSame(
                $goto,
                $this
                    ->wizard
                    ->getCurrentStep()
            );
        }

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::POST))
        ;

        if (
            $forwardOnly
            || $goto === self::NON_EXISTENT_STEP
            || $goto === self::TOO_FAR_STEP
        ) {
            $this->assertSame(
                $steps[$index + 2],
                $this
                    ->wizard
                    ->getCurrentStep()
            );
        } elseif ($autoAdvance) {
            $this->assertSame(
                $steps[$index + 1],
                $this
                    ->wizard
                    ->getCurrentStep()
            );
        } else {
            $this->assertSame(
                $steps[array_search($goto, $steps) + 1],
                $this
                    ->wizard
                    ->getCurrentStep()
            );
        }
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function step_timeout(): void
    {
        $steps = ['step_1', self::TIMEOUT_STEP, 'step_3'];

        $this->wizard = $this
            ->wizard
            ->withStepTimeout(self::STEP_TIMEOUT)
            ->withEvents([
                AfterWizard::class => [$this, 'afterWizard'],
                Step::class => [$this, 'step'],
                StepExpired::class => [$this, 'stepExpired'],
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while ($result->getStatusCode() === Status::FOUND);

        $this->assertSame(1, $this->events[StepExpired::class]);
        $this->assertSame(self::TIMEOUT_STEP, $this->wizard->getCurrentStep());
    }

    /**
     * @throws InvalidConfigException
     * @throws \Exception
     */
    #[Test]
    public function pause_and_resume(): void
    {
        $steps = ['step_1', 'step_2', self::PAUSE_STEP, 'step_4', 'step_5'];
        $expectedData = [];
        foreach ($steps as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->wizard = $this
            ->wizard
            //->withStepTimeout(self::STEP_TIMEOUT)
            ->withEvents([
                AfterWizard::class => [$this, 'afterWizard'],
                Step::class => [$this, 'step'],
                StepExpired::class => [$this, 'stepExpired'],
            ])
            ->withSteps($steps)
        ;

        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;
        } while (
            $this
                ->wizard
                ->getCurrentStep()
            !== self::PAUSE_STEP
        );

        $paused = $this
            ->wizard
            ->pause();

        self::$session->destroy();

        // Use local $wizard from here
        self::$session = new Session();
        $wizard = new Wizard(
            new Inflector(),
            new ResponseFactory(),
            self::$session,
            $this->createUrlGenerator()
        );

        $wizard->resume($paused);
        $wizard = $wizard
            ->withEvents([
                AfterWizard::class => [$this, 'afterWizard'],
                Step::class => [$this, 'step'],
                StepExpired::class => [$this, 'stepExpired'],
            ])
        ;

        do {
            $wizard->step(new ServerRequest(method: Method::GET));
            $result = $wizard->step(new ServerRequest(method: Method::POST));
        } while ($result->getStatusCode() === Status::FOUND);


        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame($expectedData, $this->data);
    }

    /**
     * @throws InvalidConfigException
     */
    #[Test]
    public function step_parameter()
    {
        $this->wizard = new Wizard(
            new Inflector(),
            new ResponseFactory(),
            self::$session,
            $this->createUrlGenerator( '/wizard/{step: \w+}')
        );

        $steps = ['step_1', self::REPEAT_STEP, 'step_3'];

        $this->wizard = $this
            ->wizard
            ->withStepParameter(self::STEP_PARAMETER)
            ->withEvents([
                Step::class => [$this, 'step'],
                AfterWizard::class => [$this, 'afterWizard']
            ])
            ->withSteps($steps)
        ;
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;

        $count = 0;
        do {
            $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::POST))
            ;

            if (str_starts_with(
                $this
                    ->wizard
                    ->getCurrentStep(),
                    self::REPEAT_STEP
            )) {
                $this->assertSame(
                    ['/wizard/' . self::REPEAT_STEP . ($count ? "_$count" : '')],
                    $result->getHeader(Header::LOCATION)
                );
                $count++;
            } elseif ($result->getStatusCode() === Status::FOUND) {
                $this->assertSame(
                    ['/wizard/' . $this->wizard->getCurrentStep()],
                    $result->getHeader(Header::LOCATION)
                );
            }
        } while ($result->getStatusCode() === Status::FOUND);
    }

    public static function autoAdvanceProvider(): Generator
    {
        foreach ([
            'Without auto advance' => ['autoAdvance' => !Wizard::AUTO_ADVANCE],
            'With auto advance' => ['autoAdvance' => Wizard::AUTO_ADVANCE],
        ] as $key => $item) {
            yield $key => $item;
        }
    }

    public static function branchProvider(): Generator
    {
        foreach ([
            'grouped branches / branch1 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::BRANCH_STEP,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => !Wizard::DEFAULT_BRANCH,
                'branches' => [
                    'branch1' => Wizard::BRANCH_ENABLED,
                    'branch2' => Wizard::BRANCH_DISABLED,
                ],
                'path' => ['step1', 'step2', self::BRANCH_STEP, 'step3', 'step4'],
            ],
            'grouped branches / branch1 / default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::BRANCH_STEP,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => Wizard::DEFAULT_BRANCH,
                'branches' => [
                ],
                'path' => ['step1', 'step2', self::BRANCH_STEP, 'step3', 'step4'],
            ],
            'grouped branches / branch2 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::BRANCH_STEP,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => !Wizard::DEFAULT_BRANCH,
                'branches' => [
                    'branch1' => Wizard::BRANCH_DISABLED,
                    'branch2' => Wizard::BRANCH_ENABLED,
                ],
                'path' => ['step1', 'step2', self::BRANCH_STEP, 'step4', 'step5'],
            ],
            'grouped branches / branch2 / default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::BRANCH_STEP,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => Wizard::DEFAULT_BRANCH,
                'branches' => [
                    'branch1' => Wizard::BRANCH_DISABLED,
                ],
                'path' => ['step1', 'step2', self::BRANCH_STEP, 'step4', 'step5'],
            ],
           'separate branches / branch1 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::BRANCH_STEP,
                    [
                        'branch1' => ['step3']
                    ],
                    'step4',
                    [
                        'branch2' => ['step5']
                    ]
                ],
                'defaultBranch' => !Wizard::DEFAULT_BRANCH,
                'branches' => [
                    'branch1' => Wizard::BRANCH_ENABLED,
                    'branch2' => Wizard::BRANCH_DISABLED,
                ],
                'path' => ['step1', 'step2', self::BRANCH_STEP, 'step3', 'step4'],
            ],
           'separate branches / branch2 / no default' => [
               'steps' => [
                   'step1',
                   'step2',
                   self::BRANCH_STEP,
                   [
                       'branch1' => ['step3']
                   ],
                   'step4',
                   [
                       'branch2' => ['step5']
                   ]
               ],
               'defaultBranch' => !Wizard::DEFAULT_BRANCH,
               'branches' => [
                   'branch1' => Wizard::BRANCH_DISABLED,
                   'branch2' => Wizard::BRANCH_ENABLED,
               ],
               'path' => ['step1', 'step2', self::BRANCH_STEP, 'step4', 'step5'],
           ],
           'separate branches / branch1 & branch2 / no default' => [
               'steps' => [
                   'step1',
                   'step2',
                   self::BRANCH_STEP,
                   [
                       'branch1' => ['step3']
                   ],
                   'step4',
                   [
                       'branch2' => ['step5']
                   ]
               ],
               'defaultBranch' => Wizard::DEFAULT_BRANCH,
               'branches' => [
               ],
               'path' => ['step1', 'step2', self::BRANCH_STEP, 'step3', 'step4', 'step5'],
           ],
        ] as $name => $branch) {
            yield $name => $branch;
        }
    }

    public static function forwardOnlyProvider(): Generator
    {
        foreach ([
            'Without forward only' => ['forwardOnly' => !Wizard::FORWARD_ONLY],
            'With forward only' => ['forwardOnly' => Wizard::FORWARD_ONLY],
        ] as $key => $item) {
            yield $key => $item;
        }
    }

    public static function gotoProvider(): Generator
    {
        foreach ([
            'Non-existent / autoAdvance / forwardOnly' => [
                'goto' => self::NON_EXISTENT_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Non-existent / !autoAdvance / forwardOnly' => [
                'goto' => self::NON_EXISTENT_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Non-existent / autoAdvance / !forwardOnly' => [
                'goto' => self::NON_EXISTENT_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
            'Non-existent / !autoAdvance / !forwardOnly' => [
                'goto' => self::NON_EXISTENT_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
            'Return to / autoAdvance / forwardOnly' => [
                'goto' => self::RETURN_TO_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Return to / !autoAdvance / forwardOnly' => [
                'goto' => self::RETURN_TO_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Return to / autoAdvance / !forwardOnly' => [
                'goto' => self::RETURN_TO_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
            'Return to / !autoAdvance / !forwardOnly' => [
                'goto' => self::RETURN_TO_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
            'Too far / autoAdvance / forwardOnly' => [
                'goto' => self::TOO_FAR_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Too far / !autoAdvance / forwardOnly' => [
                'goto' => self::TOO_FAR_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => Wizard::FORWARD_ONLY,
            ],
            'Too far / autoAdvance / !forwardOnly' => [
                'goto' => self::TOO_FAR_STEP,
                'autoAdvance' => Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
            'Too far / !autoAdvance / !forwardOnly' => [
                'goto' => self::TOO_FAR_STEP,
                'autoAdvance' => !Wizard::FORWARD_ONLY,
                'forwardOnly' => !Wizard::FORWARD_ONLY,
            ],
        ] as $key => $item) {
            yield $key => $item;
        }
    }

    public static function repeatGoBackIndexProvider(): Generator
    {
        foreach([1, 2, 3] as $index) {
            yield (string)$index => ['repeatGoBackIndex' => $index];
        }
    }

    public static function sessionKeyProvider(): Generator
    {
        foreach ([
            'default' => '',
            'TestKey'
        ] as $key => $value) {
            if (is_int($key)) {
                $key = $value;
            }

            yield $key => ['sessionKey' => $value];
        }
    }

    private function createUrlGenerator(string $stepRoutePattern = self::URI): UrlGeneratorInterface {
        $route = Route::methods([Method::GET, Method::POST], $stepRoutePattern)
            ->name(self::STEP_ROUTE)
        ;
        $routes = [$route];
        $routeCollection = $this->createRouteCollection($routes);
        $currentRoute = new CurrentRoute();
        $uri = (new UriFactory())->createUri(self::URI);
        $currentRoute->setRouteWithArguments($route, ['step']);
        return new UrlGenerator($routeCollection, $currentRoute);
    }

    private function createRouteCollection(array $routes): RouteCollectionInterface
    {
        $rootGroup = Group::create()->routes(...$routes);
        $collector = new RouteCollector();
        $collector->addGroup($rootGroup);
        return new RouteCollection($collector);
    }

    // --------------
    // Event Handlers
    // --------------
    public function afterWizard(AfterWizard $event): void
    {
        $this->events[AfterWizard::class]++;
        $this->data = $event->getWizard()->getData();
        $event->setResponse(new Response());
    }

    public function beforeWizard(BeforeWizard $event): void
    {
        $this->events[BeforeWizard::class]++;
    }

    public function step(Step $event): void
    {
        $this->events[Step::class]++;

        if ($event->getRequest()->getMethod() === Method::POST) {
            $step = $event->getWizard()->getCurrentStep();
            if ($step === self::REPEAT_STEP) {
                $event->setData([
                    'key-' . $this->index => $step . '-value-' . $this->index
                ]);
            } else {
                $event->setData(['key' => $step . '-value']);
            }

            switch ($step) {
                case self::AUTO_ADVANCE_STEP:
                    $event->setGoto(self::RETURN_TO_STEP);
                    break;
                case self::BACK_STEP:
                    $event->setGoto(Wizard::DIRECTION_BACKWARD);
                    break;
                case self::BRANCH_STEP:
                    $event->setBranches($this->branches);
                    break;
                case self::END_STEP:
                    $event->stopWizard();
                    break;
                case self::GOTO_STEP:
                    $event->setGoto($this->goto);
                    break;
                case self::REPEAT_STEP:
                    if ($this->repeatGoBack && $this->index === $this->repeatGoBackIndex) {
                        $this->index--;
                        $this->repeatGoBack = !$this->repeatGoBack;
                        $event->setGoto(Wizard::DIRECTION_BACKWARD);
                    } elseif (++$this->index < count(self::REPETITIONS)) {
                        $event->setGoto(Wizard::DIRECTION_REPEAT);
                    }
                    break;
                case self::TIMEOUT_STEP:
                    sleep(self::STEP_TIMEOUT + 1);
                    break;
                default:
                    break;
            }
        } else {
            $event->setResponse(new Response());
            if ($this->endOnGet && $event->getWizard()->getCurrentStep() === self::END_STEP) {
                $event->stopWizard();
            }
        }
    }

    public function stepExpired(StepExpired $event): void
    {
        $this->events[StepExpired::class]++;
        $this->data = $event->getWizard()->getData();
        $event->setResponse(new Response());
    }
}
