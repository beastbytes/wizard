.. code-block:: php

    class MyController
    {
        private const WIZARD_STEPS = ['step_1', 'step_2', 'step_3'];

        // Pass Step event to methods with the same name as the current step
        use StepHandlerTrait;

        public function __construct(
            private ResponseFactoryInterface $responseFactory,
            private UrlGeneratorInterface $urlGenerator,
            private ViewRenderer $viewRenderer
        )
        {
            $this->viewRenderer = $this
                ->viewRenderer
                ->withController($this)
            ;
        }

        public function wizard(
            ServerRequestInterface $request,
            WizardInterface $wizard
        ): ResponseInterface
        {
            return $wizard
                ->withEvents([
                    [$this, 'afterWizardHandler'],
                    [$this, 'stepHandler'],
                ])
                ->withForwardOnly(Wizard::FORWARD_ONLY)
                ->withSteps(self::WIZARD_STEPS)
                ->step($request)
            ;
        }

        public function afterWizardHandler(AfterWizard $event)
        {
            /*
             * $data is a map
             * keys are the step names
             * value for each key is the data for that step
             */
            $data = $event
                ->getWizard()
                ->getData()
            ;

            // Do whatever needs doing with the data,
            // e.g. persist to a database

            // When done set a redirect response in the event
            $event
                ->setResponse(
                    $this
                        ->responseFactory
                        ->createResponse(Status::FOUND)
                        ->withHeader(
                            Header::LOCATION,
                            $this
                                ->urlGenerator
                                ->generate('wizard_complete') // redirect to a new URL
                        )

                )
            ;
        }

        /**
         * Handle the Step event for step_1
         */
        private function step_1(Step $event): void
        {
            // instanceof \Yiisoft\FormModel\FormModelInterface
            $formModel = new Step1Form();

            if (
                $this
                    ->formHydrator
                    ->populateFromPostAndValidate(
                        $formModel,
                        $event->getRequest()
                    )
            ) {
                $data = $formModel->getData();
                $event->setData($data);
                return;
            }

            $response = $this
                ->viewRenderer
                ->render(
                    'step_1', // an ordinary view file
                    [
                        'formModel' => $formModel,
                    ]
                )
            ;

            $event->setResponse($response);
        }

        /**
         * Handle the Step event for step_2
         */
        private function step_2(Step $event): void
        {
            $formModel = new Step2Form();

            if (
                $this
                    ->formHydrator
                    ->populateFromPostAndValidate(
                        $formModel,
                        $event->getRequest()
                    )
            ) {
                $data = $formModel->getData();
                $event->setData($data);
                return;
            }

            $response = $this
                ->viewRenderer
                ->render(
                    'step_2',
                    [
                        'formModel' => $formModel,
                    ]
                )
            ;

            $event->setResponse($response);
        }

        /**
         * Handle the Step event for step_3
         */
        private function step_3(Step $event): void
        {
            $formModel = new Step3Form();

            if (
                $this
                    ->formHydrator
                    ->populateFromPostAndValidate(
                        $formModel,
                        $event->getRequest()
                    )
            ) {
                $data = $formModel->getData();
                $event->setData($data);
                return;
            }

            $response = $this
                ->viewRenderer
                ->render(
                    'step_3',
                    [
                        'formModel' => $formModel,
                    ]
                )
            ;

            $event->setResponse($response);
        }
    }