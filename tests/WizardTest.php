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
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Session;

class WizardTest extends TestCase
{
    private const COMPLETED_ROUTE = 'completedRoute';
    private const COMPLETED_ROUTE_PATTERN = '/completed';
    private const EXPIRED_ROUTE = 'expiredRoute';
    private const EXPIRED_ROUTE_PATTERN = '/expired';
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
    private const STEP_ROUTE = 'stepRoute';
    private const STEP_ROUTE_PATTERN = '/wizard';
    private const TIMEOUT_STEP = 'timeout_step';
    private const STEP_TIMEOUT = 2;

    private array $branches;
    private bool $continue;
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
        $this->continue = true;
        $this->endOnGet = false;
        $this->repeatGoBack = false;

        $this->wizard = new Wizard(
            new Dispatcher($this->createProvider()),
            new ResponseFactory(),
            self::$session,
            $this->createUrlGenerator()
        );
    }

    protected function tearDown(): void
    {
        $this
            ->wizard
            ->reset()
        ;
    }

    #[DataProvider('sessionKeyProvider')]
    public function test_session_key(string $sessionKey): void
    {
        if ($sessionKey === '') {
            $sessionKey = Wizard::SESSION_KEY;
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

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_no_completed_route(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(strtr(Wizard::ROUTE_NOT_SET_EXCEPTION, ['{route}' => self::COMPLETED_ROUTE]));
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_no_step_route(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(strtr(Wizard::ROUTE_NOT_SET_EXCEPTION, ['{route}' => self::STEP_ROUTE]));
        $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->step(new ServerRequest(method: Method::GET))
        ;
    }

    public function test_no_steps(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(Wizard::STEPS_NOT_SET_EXCEPTION);
        $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->step(new ServerRequest(method: Method::GET))
        ;
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    public function test_start_wizard(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        $result = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
            ->step(new ServerRequest(method: Method::GET))
        ;

        $this->assertSame(1, $this->events[BeforeWizard::class]);
        $this->assertSame(
            $steps,
            self::$session->get(Wizard::SESSION_KEY . '.' . Wizard::STEPS_KEY)
        );
        $this->assertSame(Status::FOUND, $result->getStatusCode());
        $this->assertTrue($result->hasHeader(Header::LOCATION));
        $this->assertSame([self::STEP_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    public function test_start_but_dont_run_wizard(): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        $this->continue = false;

        $result = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
            ->step(new ServerRequest(method: Method::GET))
        ;

        $this->assertEmpty($this->wizard->getData());
        $this->assertSame([self::COMPLETED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    #[DataProvider('autoAdvanceProvider')]
    public function test_steps(bool $autoAdvance): void
    {
        $steps = ['step_1', 'step_2', 'step_3'];
        $expectedData = [];
        foreach ($steps as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withAutoAdvance($autoAdvance)
            ->withSteps($steps)
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
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(count($steps), $count);
        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame([self::COMPLETED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));

        $this->assertSame(
            $expectedData,
            $this
                ->wizard
                ->getData()
        );

        for ($i = 0; $i < $count; $i++) {
            $this->assertSame(
                $expectedData[$steps[$i]],
                $this
                    ->wizard
                    ->getData($steps[$i])
            );
        }
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    public function test_end_on_get(): void
    {
        $steps = ['step_1', 'step_2', 'step_3', self::END_STEP, 'step_4', 'step_6'];
        $expectedData = [];

        $this->endOnGet = true;

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
        ;

        $index = array_search(self::END_STEP, $steps, true) + 1;
        for ($i = 0; $i < $index; $i++) {
            if ($i < $index - 1) {
                $expectedData[$steps[$i]] = ['key' => $steps[$i] . '-value'];
            }
        }

        $count = 0;
        do {
            $result = $this
                ->wizard
                ->step(new ServerRequest(method: Method::GET))
            ;

            if ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]) {
                $result = $this
                    ->wizard
                    ->step(new ServerRequest(method: Method::POST))
                ;
            }
            $count++;
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame($expectedData, $this->wizard->getData());
        $this->assertSame([self::COMPLETED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
    }

    public function test_end_on_post(): void
    {
        $steps = ['step_1', 'step_2', 'step_3', self::END_STEP, 'step_4', 'step_6'];
        $expectedData = [];

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
        ;

        $index = array_search(self::END_STEP, $steps, true) + 1;
        for ($i = 0; $i < $index; $i++) {
            $expectedData[$steps[$i]] = ['key' => $steps[$i] . '-value'];
        }

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
            $count++;
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame([self::COMPLETED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
        $this->assertSame(
            $expectedData,
            $this
                ->wizard
                ->getData()
        );
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    #[DataProvider('forwardOnlyProvider')]
    public function test_direction_backward(bool $forwardOnly): void
    {
        $steps = ['step_1', 'step_2', self::BACK_STEP, 'step_4'];
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withForwardOnly($forwardOnly)
            ->withSteps($steps)
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
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    #[DataProvider('autoAdvanceProvider')]
    public function test_auto_advance(bool $autoAdvance): void
    {
        $steps = ['step_1', self::RETURN_TO_STEP, 'step_3', 'step_4', self::AUTO_ADVANCE_STEP, 'step_6'];
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withAutoAdvance($autoAdvance)
            ->withSteps($steps)
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
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    #[DataProvider('branchProvider')]
    public function test_branching(array $steps, bool $defaultBranch, array $branches, array $path): void
    {
        $expectedData = [];

        $this->branches = $branches;
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withDefaultBranch($defaultBranch)
            ->withSteps($steps)
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

        $this->assertSame(
            $expectedData,
            $this
                ->wizard
                ->getData()
        );
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_direction_repeat(): void
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
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
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
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(
            $expectedData,
            $this
                ->wizard
                ->getData()
        );
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    #[DataProvider('repeatGoBackIndexProvider')]
    public function test_direction_backward_in_repeated_steps(int $repeatGoBackIndex): void
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
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
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
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(
            $expectedData,
            $this
                ->wizard
                ->getData()
        );
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    #[DataProvider('gotoProvider')]
    public function test_goto_step(string $goto, bool $autoAdvance, bool $forwardOnly): void
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
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withAutoAdvance($autoAdvance)
            ->withForwardOnly($forwardOnly)
            ->withSteps($steps)
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
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_timeout_step_no_expired_route(): void
    {
        $steps = ['step_1', self::TIMEOUT_STEP, 'step_3'];

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withStepTimeout(self::STEP_TIMEOUT)
            ->withSteps($steps)
        ;

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(strtr(Wizard::ROUTE_NOT_SET_EXCEPTION, ['{route}' => self::EXPIRED_ROUTE]));
        $this
            ->wizard
            ->step(new ServerRequest(method: Method::GET))
        ;
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_step_timeout(): void
    {
        $steps = ['step_1', self::TIMEOUT_STEP, 'step_3'];

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withExpiredRoute(self::EXPIRED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withStepTimeout(self::STEP_TIMEOUT)
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
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame([self::EXPIRED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
    }

    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     * @throws \BeastBytes\Wizard\Exception\RuntimeException
     */
    public function test_pause_and_resume(): void
    {
        $steps = ['step_1', 'step_2', self::PAUSE_STEP, 'step_4', 'step_5'];
        $expectedData = [];
        foreach ($steps as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withExpiredRoute(self::EXPIRED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withStepTimeout(self::STEP_TIMEOUT)
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
            new Dispatcher($this->createProvider()),
            new ResponseFactory(),
            self::$session,
            $this->createUrlGenerator()
        );

        $wizard->resume($paused);

        do {
            $wizard->step(new ServerRequest(method: Method::GET));
            $result = $wizard->step(new ServerRequest(method: Method::POST));
        } while ($result->getHeader(Header::LOCATION) === [self::STEP_ROUTE_PATTERN]);

        $this->assertSame(1, $this->events[AfterWizard::class]);
        $this->assertSame([self::COMPLETED_ROUTE_PATTERN], $result->getHeader(Header::LOCATION));
        $this->assertSame($expectedData, $wizard->getData());
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

    public static function stepParameterProvider(): Generator
    {
        foreach ([
            'step',
            'page',
            'item',
        ] as $parameter) {
            yield ['stepParameter' => $parameter];
        }
    }

    private function createProvider(): Provider
    {
        return new Provider((new ListenerCollection())
            ->add([$this, 'afterWizard'], AfterWizard::class)
            ->add([$this, 'beforeWizard'], BeforeWizard::class)
            ->add([$this, 'step'], Step::class)
            ->add([$this, 'stepExpired'], StepExpired::class)
        );
    }

    private function createUrlGenerator(): UrlGeneratorInterface {
        $routes = [
            Route::get(self::COMPLETED_ROUTE_PATTERN)
                 ->name(self::COMPLETED_ROUTE),
            Route::get(self::EXPIRED_ROUTE_PATTERN)
                 ->name(self::EXPIRED_ROUTE),
            Route::methods([Method::GET, Method::POST], self::STEP_ROUTE_PATTERN)
                 ->name(self::STEP_ROUTE),
        ];
        $routeCollection = $this->createRouteCollection($routes);
        return new UrlGenerator($routeCollection);
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
    }

    public function beforeWizard(BeforeWizard $event): void
    {
        $event->continue($this->continue);
        $this->events[BeforeWizard::class]++;
    }

    public function step(Step $event): void
    {
        $this->events[Step::class]++;

        if ($event->getWizard()->getCurrentStep() === self::END_STEP && $this->endOnGet) {
            $event->continue(false);
        }

        if ($event->getRequest()->getMethod() === Method::POST) {
            $step = $event->getWizard()->getCurrentStep();
            if ($step === self::REPEAT_STEP) {
                $event->setData([
                    'key-' . $this->index => $event->getWizard()->getCurrentStep() . '-value-' . $this->index
                ]);
            } else {
                $event->setData(['key' => $event->getWizard()->getCurrentStep() . '-value']);
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
                    $event->continue(false);
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
        }
    }

    public function stepExpired(StepExpired $event): void
    {
        $this->events[StepExpired::class]++;
    }
}
