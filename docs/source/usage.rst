Usage
=====

Wizard is used in an action that all steps use to render and submit forms,
simplifying routing - there is only one route for a Wizard.

Wizard is event driven and the application needs to provide the event handlers;
the `Step` event handler being key to Wizard's operation - see :ref:`event_handlers`.

Wizard Settings
---------------
Wizard has a number of settings that affect its operation.

Required
++++++++

Events
******
Wizard is event driven.

See :ref: `events` for a full description of Wizard events.

see :php:meth:`Wizard::withEvents`.

Steps
*****

Steps define the actions the Wizard takes as it progresses.

At its simplest, the steps array is a list of steps for Wizard to process.

Wizard supports :ref: `pbn`, enabling Wizard to process different steps in the steps array depending on user input.

:php:meth:`withSteps`

Optional
++++++++

Auto Advance
************

Determines what the next step will be if the user has returned to an earlier step.

By default, Wizard will auto-advance to the first unprocessed step.

The alternative behaviour is to go to the next step in the steps array.

Consider a steps array consisting of five steps. If the user has completed steps 1, 2, 3, & 4 and returns to step 2.
On re-completion of step 2, by default Wizard will auto-advance to the first unprocessed step (step 5) by default.
If auto-advance is disabled (`Wizard::withAutoAdvance(!Wizard::AUTO_ADVANCE)`) Wizard will go to step 3 (i.e. reprocess the steps array)

See :php:meth:`Wizard::withAutoAdvance`

Default Branch
**************

Determines how branch groups are handled if no branch is explicitly enabled.

By default, Wizard will use the first branch in a branch group unless another branch is enabled.

The alternative behaviour is to skip the branch group unless a branch is enabled.

See :php:meth:`withDefaultBranch`

Forward Only
************

Determines whether Wizard allows returning to steps that have been processed

By default Wizard allows returning to previously processed steps.

The alternative behaviour is to allow Wizard to move forward through the steps array.

See :php:meth:`withForwardOnly`

Session Key
***********

The default session key is `__wizard`. This can be changed.

See :php:meth:`withSessionKey`

Step Parameter
**************

The default step parameter is **WHAT**. This can be changed.

See :php:meth:`withStepParameter`

Step Timeout
************

A step timeout can be set to ensure a user responds within a given time.

By default Wizard does not have a step timeout.

If a step timeout is set and a user does not complete a step within the timeout Wizard raises the :ref:`_stepexpired`

See :php:meth:`withStepTimeout`

Example
-------

.. code-block:: php

    class MyController
    {
        private const WIZARD_STEPS = ['step_1', 'step_2', 'step_3'];

        public function wizardAction(ServerRequestInterface $request, WizardInterface $wizard): ResponseInterface
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
            // Handle AfterWizard event
        }

        public function stepHandler(Step $event)
        {
            // Handle Step event
        }
    }

The above example does the following:

* Declares the steps in WIZARD_STEPS.
    There can be as many steps as needed and this array also defines branch options when
    Plot Branching Navigation (PBN) is used (more on PBN later).

* Declares an action (the `wizardAction()` method in this example). This action is used for all Wizard steps.

* The action sets the Wizard options, declares the events and their handlers, then calls `Wizard::step()`.

* Declares the `AfterWizard` and `Step` event handlers.
    The `AfterWizard` and `Step` events *must* be handled, the `StepExpired` event *must* be handled
    if a step timeout is specified, handling the `BeforeWizard` event is optional.
    The example shows the event handlers in the controller; this is not a requirement.
