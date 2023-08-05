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
use BeastBytes\Wizard\Wizard;
use Generator;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use InvalidArgumentException;
use phpDocumentor\Reflection\Types\Self_;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
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
    private const EXPIRED_ROUTE_PATTERN = '/expired/{' . self::STEP_PARAMETER . ':\w*}';
    private const STEP_BACK = 'step_back';
    private const STEP_BRANCH = 'step_branch';
    private const STEP_ROUTE = 'stepRoute';
    private const STEP_PARAMETER = 'step';
    private const STEP_ROUTE_PATTERN = '/wizard/{' . self::STEP_PARAMETER . ':\w*}';
    private const TEST_DATA_KEY = 'testData';

    private array $branches;
    private array $events;
    private ResponseFactory $responseFactory;
    private static Session $session;
    private array $testData;
    private UrlGeneratorInterface $urlGenerator;
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

        $provider = new Provider((new ListenerCollection())
            ->add([$this, 'afterWizard'], AfterWizard::class)
            ->add([$this, 'beforeWizard'], BeforeWizard::class)
            ->add([$this, 'step'], Step::class)
            ->add([$this, 'stepExpired'], StepExpired::class)
        );

        $this->responseFactory = new ResponseFactory();
        $this->urlGenerator = $this->createUrlGenerator();

        $this->wizard = new Wizard(
            new Dispatcher($provider),
            $this->responseFactory,
            self::$session,
            $this->urlGenerator
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
    public function test_session_key(string $sessionKey)
    {
        if ($sessionKey === '') {
            $sessionKey = Wizard::SESSION_KEY;
        } else {
            $this->wizard = $this->wizard->withSessionKey($sessionKey);
        }

        $reflectionClass = new ReflectionClass($this->wizard);
        $reflectionProperty = $reflectionClass->getProperty('sessionKey');
        $reflectionProperty->setAccessible(true);

        $this->assertSame($sessionKey, $reflectionProperty->getValue($this->wizard));
    }

    public function test_no_completed_route()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(strtr(Wizard::ROUTE_NOT_SET_EXCEPTION, ['{route}' => self::COMPLETED_ROUTE]));
        $this
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;
    }

    public function test_no_step_route()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(strtr(Wizard::ROUTE_NOT_SET_EXCEPTION, ['{route}' => self::STEP_ROUTE]));
        $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;
    }

    public function test_no_steps()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage(Wizard::STEPS_NOT_SET_EXCEPTION);
        $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;
    }

    public function test_not_started()
    {
        $steps = ['name', 'address', 'phone'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(strtr(Wizard::INVALID_STEP_EXCEPTION, ['{step}' => $steps[0]]));
        $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
            ->step($steps[0], new ServerRequest(method: Method::GET))
        ;
    }

    public function test_start_wizard()
    {
        $steps = ['name', 'address', 'phone'];
        $result = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;

        $this->assertSame(1, $this->events[BeforeWizard::class]);
        $this->assertSame(
            array_combine($steps, $steps),
            self::$session->get(Wizard::SESSION_KEY . '.' . Wizard::STEPS_KEY)
        );
        $this->assertSame(Status::FOUND, $result->getStatusCode());
        $this->assertTrue($result->hasHeader(Header::LOCATION));
        $this->assertSame(['/wizard/name'], $result->getHeader(Header::LOCATION));
    }

    public function test_steps()
    {
        $steps = ['name', 'address', 'phone'];
        $expectedData = [];
        foreach ($steps as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withSteps($steps)
        ;

        $this // start the wizard
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;

        $this->assertSame(1, $this->events[BeforeWizard::class]);

        for ($i = 0; $i < count($steps);) {
            $result = $this
                ->wizard
                ->step($steps[$i], new ServerRequest(method: Method::GET))
            ;
            $this->assertSame(Status::FOUND, $result->getStatusCode());
            $this->assertTrue($result->hasHeader(Header::LOCATION));
            $this->assertSame(['/wizard/' . $steps[$i]], $result->getHeader(Header::LOCATION));

            $result = $this
                ->wizard
                ->step($steps[$i], new ServerRequest(method: Method::POST))
            ;
            $this->assertSame(Status::FOUND, $result->getStatusCode());
            $this->assertTrue($result->hasHeader(Header::LOCATION));

            $step = ++$i < count($steps) ? $steps[$i] : Wizard::EMPTY_STEP;

            $this->assertSame(['/wizard/' . $step], $result->getHeader(Header::LOCATION));
        }

        $this // end the wizard
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;

        $this->assertSame(1, $this->events[AfterWizard::class]);

        $this->assertSame($expectedData, $this->wizard->getData());
    }

    public function test_previous_step()
    {
        $steps = ['step_0', self::STEP_BACK, 'step_2'];
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withForwardOnly(!Wizard::FORWARD_ONLY)
            ->withSteps($steps)
        ;

        $this
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step($steps[0], new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step($steps[0], new ServerRequest(method: Method::POST))
        ;
        $this
            ->wizard
            ->step($steps[1], new ServerRequest(method: Method::GET))
        ;
        $result = $this
            ->wizard
            ->step($steps[1], new ServerRequest(method: Method::POST))
        ;

        $this->assertSame(['/wizard/' . $steps[0]], $result->getHeader(Header::LOCATION));
    }

    public function test_no_previous_step_if_forward_only()
    {
        $steps = ['step_0', self::STEP_BACK, 'step_2'];
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withForwardOnly(Wizard::FORWARD_ONLY)
            ->withSteps($steps)
        ;

        $this
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step($steps[0], new ServerRequest(method: Method::GET))
        ;
        $this
            ->wizard
            ->step($steps[0], new ServerRequest(method: Method::POST))
        ;
        $this
            ->wizard
            ->step($steps[1], new ServerRequest(method: Method::GET))
        ;
        $result = $this
            ->wizard
            ->step($steps[1], new ServerRequest(method: Method::POST))
        ;

        $this->assertSame(['/wizard/' . $steps[2]], $result->getHeader(Header::LOCATION));
    }

    #[DataProvider('branchProvider')]
    public function test_branching(array $steps, bool $defaultBranch, array $branches, array $path)
    {
        $expectedData = [];
        foreach ($path as $step) {
            $expectedData[$step] = ['key' => $step . '-value'];
        }

        $this->branches = $branches;
        $this->wizard = $this
            ->wizard
            ->withCompletedRoute(self::COMPLETED_ROUTE)
            ->withStepRoute(self::STEP_ROUTE)
            ->withDefaultBranch($defaultBranch)
            ->withSteps($steps)
        ;

        $this
            ->wizard
            ->step(Wizard::EMPTY_STEP, new ServerRequest(method: Method::GET))
        ;

        foreach ($path as $i => $step) {
            $this
                ->wizard
                ->step($step, new ServerRequest(method: Method::GET))
            ;
            $result = $this
                ->wizard
                ->step($step, new ServerRequest(method: Method::POST))
            ;

            if (($index = $i + 1) < count($path)) {
                $this->assertSame(['/wizard/' . $path[$index]], $result->getHeader(Header::LOCATION));
            }
        }

        $this->assertSame($expectedData, $this->wizard->getData());
    }

    public static function branchProvider(): Generator
    {
        foreach ([
            //*
            'grouped branches / branch1 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::STEP_BRANCH,
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
                'path' => ['step1', 'step2', self::STEP_BRANCH, 'step3', 'step4'],
            ],
            //*/
            //*
            'grouped branches / branch1 / default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::STEP_BRANCH,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => Wizard::DEFAULT_BRANCH,
                'branches' => [
                ],
                'path' => ['step1', 'step2', self::STEP_BRANCH, 'step3', 'step4'],
            ],
            //*/
            //*
            'grouped branches / branch2 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::STEP_BRANCH,
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
                'path' => ['step1', 'step2', self::STEP_BRANCH, 'step4', 'step5'],
            ],
            //*/
            //*
            'grouped branches / branch2 / default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::STEP_BRANCH,
                    [
                        'branch1' => ['step3', 'step4'],
                        'branch2' => ['step4', 'step5']
                    ]
                ],
                'defaultBranch' => Wizard::DEFAULT_BRANCH,
                'branches' => [
                    'branch1' => Wizard::BRANCH_DISABLED,
                ],
                'path' => ['step1', 'step2', self::STEP_BRANCH, 'step4', 'step5'],
            ],
            //*/
            //*
           'separate branches / branch1 / no default' => [
                'steps' => [
                    'step1',
                    'step2',
                    self::STEP_BRANCH,
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
                'path' => ['step1', 'step2', self::STEP_BRANCH, 'step3', 'step4'],
            ],
            //*/
           //*
           'separate branches / branch2 / no default' => [
               'steps' => [
                   'step1',
                   'step2',
                   self::STEP_BRANCH,
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
               'path' => ['step1', 'step2', self::STEP_BRANCH, 'step4', 'step5'],
           ],
           //*/
           //*
           'separate branches / branch1 & branch2 / no default' => [
               'steps' => [
                   'step1',
                   'step2',
                   self::STEP_BRANCH,
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
               'path' => ['step1', 'step2', self::STEP_BRANCH, 'step3', 'step4', 'step5'],
           ],
           //*/
        ] as $name => $branch) {
            yield $name => $branch;
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

    public function afterWizard(AfterWizard $event): void
    {
        $this->events[AfterWizard::class]++;
    }

    public function beforeWizard(BeforeWizard $event): void
    {
        $this->events[BeforeWizard::class]++;
    }

    public function step(Step $event): void
    {
        $this->events[Step::class]++;

        if ($event->getRequest()->getMethod() === Method::POST) {
            if ($event->getWizard()->getCurrentStep() === self::STEP_BACK) {
                $event->setGoto(Wizard::DIRECTION_BACKWARD);
            } else {
                if ($event->getWizard()->getCurrentStep() === self::STEP_BRANCH) {
                    $event->setBranches($this->branches);
                }

                $event->setData(['key' => $event->getWizard()->getCurrentStep() . '-value']);
            }
        }
    }

    public function stepExpired(StepExpired $event): void
    {
        $this->events[StepExpired::class]++;
    }
}
