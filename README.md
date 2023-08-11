# Wizard
Wizard to handle multi-step forms.

## Features

- All forms submit to the same URL
- Next/Previous or Forward Only navigation
- Looping
  - repeat one or more steps on a form as many times as needed
- Plot Branching Navigation (PBN)
  - allows the form to decide which path to take depending on the user's response
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
"beastbytes/wizard": "^1.0"
```
to the require section of your composer.json.

## Usage
For more explanation and examples see the [Wiki](https://github.com/beastbytes/wizard/wiki).
### Controller
#### Constructor
Inject the Wizard in the controller's constructor and initialise it.

```php
public function __construct(
    // other constructor injections
    private WizardInterface $wizard)
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
public function wizard(ServerRequestInterface $request): ResponseInterface
{
    return $this
        ->wizard
        ->step($request)
    ;
}
```
_TIP:_ BeastBytes\Wizard\WizardTrait provides the wizard action.

### Events
A number of events are raised as the wizard runs. All events can retrieve the wizard instance using
```php
$this->getWizard();
```

##### BeforeWizard
Raised before the wizard processes any steps. The wizard can be prevented from running with 
```php
BeforeWizard::continue(false);
```

##### Step
Raised when processing a step. The event handler for this event does what actions normally do, i.e. rendering the view, and data validation and saving on form submission; this event is raised twice for each step.

  1. The first time this event is raised the event handler is responsible for rendering the appropriate form in a view.
  2. The second time the event handler is responsible for data validation and setting the data into the event.

##### Request
The event handler uses
```php
$this
    ->getWizard()
    ->getRequest()
;
```
to determine the type (Method::GET or Method::POST) of request.

##### CurrentStep
The event handler uses
```php
$this
    ->getWizard()
    ->getCurrentStep()
;
```
to determine the step being processed.

##### Saving Data
The event handler uses
```php
$this->saveData($data);
```
to save data for the step.

_TIP:_ Use the same for a repeated step; the wizard will correctly save data for all repeated steps.

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

##### AfterWizard
Raised after the wizard has finished. This event handler is responsible for retrieving data from the wizard and saving it to models.

Date is retrieved from the wizard using
```php
$this
    ->getWizard()
    ->getData()
;
```
to get the data for all steps, or
```php
$this
    ->getWizard()
    ->getData('stepName')
;
```
to get the data for a specific step.

##### StepExpired
Raised when processing a step has expired. If this event is raised, the step data will be stored in the Wizard.
