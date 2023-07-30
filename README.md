# Wizard
Wizard to handle multi-step forms.

## Features

- All forms submit to the same route
  - user friendly URLs
- Next/Previous or Forward Only navigation
- Looping
  - repeat one or more steps on a form as many times as needed
- Plot Branching Navigation (PBN)
  - allows the form to decide which path to take depending on a user's response
- Step timeout
  - steps can have a timeout to ensure a user responds within a given time
- Save/Restore
  - save partially completed forms; restore and continue from that point
- Event driven
  - write the handler functions and hook them up to events

For license information see the [LICENSE](LICENSE.md)-file.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

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
### Controller
#### Constructor
Inject the Wizard in the controller's constructor.

#### Action
The controller action is very simple:
```php
public function wizard(
    Request $request,
    CurrentRoute $currentRoute
): ResponseInterface
{
    $step = $currentRoute->getArgument('step');
    $method = $request->getMethod();
    return $this->step($step, $method);
}
```

### Events
##### BeforeWizard
Raised before the wizard processes any steps.

##### AfterWizard
Raised after the wizard has finished. The event handler is responsible for retrieving data from the wizard and 
saving it to models.

##### Step
Raised when processing a step. This event is raised twice for each step. The first time it is raised the event
handler is responsible for rendering the appropriate form in a view. The second time the event handler is 
responsible for data validation and setting the data into the event.

##### StepExpired
Raised when processing a step has expired. If this event is raised the step data will be stored in the Wizard.
