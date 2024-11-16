# Wizard
Wizard to handle multistep forms.

## Features

- All forms submit to the same URL
- Next/Previous or Forward Only navigation
- Looping
  - repeat one or more steps as many times as needed
- Plot Branching Navigation (PBN)
  - decide which path to take depending on the user's response
- Step Timeout
  - steps can have a timeout to ensure a user responds within a given time
- Save/Restore
  - save partially completed forms; restore and continue from that point
- Event driven
  - write the handler functions and hook them up to events

For license information see the [LICENSE](LICENSE.md) file.

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run
```
php composer.phar require --prefer-dist beastbytes/wizard
```
or add
```json
"beastbytes/wizard": "*"
```
to the 'require' section of your composer.json.

## Usage
For more explanation and examples see the [Wiki](https://github.com/beastbytes/wizard/wiki).

```php
public function __construct(
    // other constructor injections
    private WizardInterface $wizard
): void
{
    // other initialisation
    $this->wizard = $this // minimal wizard initialisation
        ->wizard
        ->withCompletedRoute(self::COMPLETED_ROUTE)
        ->withStepRoute(self::STEP_ROUTE)
        ->withSteps(['step_1', 'step_2', 'step_3', ..., 'step_n'])
    ;
}
```

#### Action
The controller action is very simple:
```php
public function wizard(ServerRequestInterface $request,  WizardInterface $wizard): ResponseInterface
{
    return $wizard
        ->withId(self::class) // used when the app uses more than one wizard
        ->withForwardOnly(true)
        ->withSteps([
            'step1',
            'step2',
            'step3',
              ///
            'stepN',
        ])
        ->step($request)
    ;
}
```

### Events
A number of events are raised as the wizard runs:

+ BeforeWizard - Raised before any steps are processed.
+ Step - Raised twice for each step
+ AfterWizard - Raised after all steps are completed
+ StepExpired - Raised if a step has expired

The event handlers are usually methods in the controller using the wizard.

Event handlers can access the wizard instance using
```php
$wizard = $event->getWizard();
```

If the application uses more than one wizard, event handlers should ensure that they are the one to handle the event by
checking the wizard id:
```php
$wizardId = $event->getWizard()->getId();
```

All Wizard events implement the StoppableEventInterface, so if an event handler did handle the event it should stop
propagation of the event:
```php
$event->stopPropagation();
```

##### BeforeWizard
Raised before the wizard processes any steps. The event handler can prevent the wizard from running with 
```php
$event->stopWizard();
```

##### Step
Raised when processing a step. This event is raised at least twice for each step. In many ways the event handler for
this event is like an action: the first time the event is raised the event handler should render the form for the step
saving the response to the event, then for the second and subsequent times the event is raised the form has been
submitted and the event handler validates the form and then either saves the submitted data to the event if
validation passes or renders the form again if validation fails.

The event handler can determine which step the event is being raised for from the currentStep value:
```php
$currentStep = $event->getWizard()->getCurrentStep();
```
One technique to keep things tidy and make step event handling more like actions is for the event handler to call
methods for each individual step.

__TIP:__ Use StepHandlerTrait in the controller; it both checks the Wizard ID and calls methods named for each step.

Methods that render then validate a step's form will look something like:
```php
public function step1Handler(StepEvent $event): void
{
    $formModel = new Step1Form(); // Step1Form is the form model for step1

    if ($this->formHydrator->populateFromPostAndValidate($formModel, $event->getRequest())) {
        $data = $formModel->getData(); // typically an array of data fields <fieldName => fieldValue> 
        $event->setData($data);        
        return; // the wizard creates the response
    }

    $response = $this->viewRenderer->render('step1', ['formModel' => $formModel]);
    $event->setResponse($response); // set the response in the event, do not return it
}
```
__TIP:__ See the examples for more complex usage: repeated steps, branching, etc.

##### AfterWizard
Raised after the wizard has finished. The event handler for this event is responsible for retrieving data from the
wizard and acting on it, e.g. persisting to a database

Data is retrieved from the wizard using:
```php
$event->getWizard()->getData();
```
If only the data for a specific step is required:
```php
$event->getWizard()->getData('stepName');
```

The event handler must also render the view indication completion of the wizard and set the response in the event.

The event handler will look something like:
```php
public function wizardCompletedHandler(StepEvent $event): void
{
    $data = $event->getWizard()->getData();
    
    // Save data to database
    // Raise any application events

    $response = $this->viewRenderer->render('wizardCompleted');
    $event->setResponse($response); // set the response in the event, do not return it
}
```

##### StepExpired
Raised when processing a step has expired. If this event is raised, the data for all processed steps - including the one
that expired - will be stored in the Wizard. The event handler should as a minimum render a view and set the response in
the event; it may also persist data.

The event handler will look something like:
```php
public function stepExpiredHandler(StepEvent $event): void
{
    $data = $event->getWizard()->getData();
    $expiredStep = $event->getWizard()->getCurrentStep();
    
    // Do something with the data
    // Raise any application events

    $response = $this->viewRenderer->render('stepExpired', ['expiredStep' => $expiredStep]);
    $event->setResponse($response); // set the response in the event, do not return it
}
```





#### From Step



##### Next Step
By default the wizard will move to the next step in the steps array, taking into account any active branches. The event handler can tell the wizard to go to an earlier step or repeat a step using
```php
$this->setGoto($goto);
```
where _$goto_ is one of:
* _Wizard::DIRECTION_BACKWARD_ the wizard goes to the previous step
* _Wizard::DIRECTION_FORWARD_ the wizard goes to the next step; default behaviour)
* _Wizard::REPEAT_ the wizard repeats the current step; the event handler is responsible for determining how many times the step is repeated
* 'stepName' the wizard returns to the given step; the step **must be** an earlier step.

##### Branching
THe event handler is responsible for deciding which branches (if any) in the steps array to take; use
```php
$this->branches($branches);
```
where _$branches_ is a map: _['branchName' => BranchDirective]_ where _BranchDirective_ is _Wizard::BRANCH_DISABLED_ or _Wizard::BRANCH_ENABLED_.
