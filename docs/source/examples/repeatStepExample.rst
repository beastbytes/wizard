.. code-block:: php

    class MyController
    {
        private const MAX_STEP2 = 3;
        private const WIZARD_STEPS = ['step_1', 'step_2', 'step_3'];

        // pass Step event to methods with the same name as the current step
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
                                ->generate('wizard_complete')
                        )

                )
            ;
        }

        private function step_1(StepEvent $event): void
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

        // This step can repeat
        private function step_2(StepEvent $event): void
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

                // Set the data in the normal way
                $event->setData($data);

                /*
                 * The form has a submit button called `add` as well as the normal submit button.
                 * If `add` was used to submit the form and max count has not been reached repeat the step.
                 */
                $wizard = $event->getWizard();

                $currentStep = $wizard->getCurrentStep();
                $stepData = $wizard->getData($currentStep);

                /*
                 * The format of step data is application specific so the application must check
                 * how many repetitions there have been.
                 * If more than one step's data has been saved the data is an array of step data.
                 * If the application saves form data "as is" - an array of key=>value pairs,
                 * if the first key is a string the count is 1,
                 * if the first key is an int the count is the size of the array,
                 * plus 1 for the data from this repetition
                 */
                $maxCountReached = $this
                    ->isMaxCountReached(
                        $currentStep,
                        $stepData
                    )
                ;

                // Repeat the step if allowed
                if (!$maxCountReached && $form->getAdd()) {
                    $event->setGoto(WIZARD::DIRECTION_REPEAT);
                }

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

        private function step_3(StepEvent $event): void
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