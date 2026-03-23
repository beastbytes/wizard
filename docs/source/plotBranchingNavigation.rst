.. _pbn:

Plot Branching Navigation
=========================

Plot Branching Navigation (PBN) allows different sequences of steps to be processed depending on user input.
An example of PBN is surveys and questionnaires where different sets of questions - branches - are completed
depending on the answer to a particular question; e.g. "If you answered *Yes* to Q3 goto Q10, else goto Q4"

Wizard allows PBN by defining all the possible branches in the steps array
and choosing the appropriate branch in the `Step event handler`.

Steps Array
-----------
The steps array definition is:

ABNF Definition of Steps Array
++++++++++++++++++++++++++++++
c = "_" / ALPHA

Step = c \*(c / DIGIT)

Branch = 1\*(c / DIGIT / "-")

BranchGroup = "[" 1\*(Branch "=>[" 1\*(Step / BranchGroup) "],") "]"

StepsArray = "[" Step "," \*(Step / BranchGroup ",") "]"

* step names can consist of underscores, alphabetic characters and digits. They *must* start with an underscore or alphabetic character.
* branch names can consist of underscores, dashes, alphabetic characters and digits.
* a branch group is an array of steps and/or branch groups whose keys are the branch names
* the first entry in the steps array *must* be a step; subsequent entries can be steps or branch groups.

Note that a *branch group* can contain a *branch group*.
This enables complex paths through the steps array to be defined.

Example Steps Array
+++++++++++++++++++

.. code-block:: php

    $steps = [
        'make',
        'model',
        'fuel',
        [
            'fossil' => [
                'tank_capacity',
                'consumption',
            ],
            'electric' => [
                'battery_capacity',
                'home_charging',
            ]
        ],
        'range'
    ];

This example defines steps about a vehicle. Different steps are taken depending on the type of fuel.

Deciding which Branch to Use
++++++++++++++++++++++++++++

Deciding which branch to use is done in the `Step` event handler;
the event handler calls the event's `setBranches()` method with an array whose keys are branch names and values are
either `Wizard::BRANCH_DISABLED` or `Wizard::BRANCH_ENABLED`; only one branch must be enabled.

In the example steps array above, the *fuel* step form will return the type of fuel the car uses;
from the answer the step handler can decide which branch to take.
The Step event handler for the *fuel* step will be something like:

.. code-block:: php

    private function fuel(Step $event)
    {
        $formModel = new FuelForm();

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

            // Decide which branch to take
            if ($data['fuel'] === self::FUEL_ELECTRIC) {
                $event->setBranches([
                    'fossil' => Wizard::BRANCH_DISABLED
                    'electric' => Wizard::BRANCH_ENABLED
                ]);
            }

            return;
        }

        $response = $this
            ->viewRenderer
            ->render('fuel', ['formModel' => $formModel])
        ;

        $event->setResponse($response);
    }

Default Branch
++++++++++++++

In the above example the fuel type checked and if it's `FUEL_ELECTRIC` the *fossil* branch is disabled
and the *electric* branch is enabled.

The handler does not enable the *fossil* branch if the fuel is not `FUEL_ELECTRIC`;
because the *fossil* branch is defined first in the branch group it is the default branch and automatically taken unless
it is disabled.

This behaviour can be disabled by calling `Wizard::withDefaultBranch(!Wizard::DEFAULT_BRANCH)` when setting up Wizard.
This will skip all branches in a branch group unless a branch is specifically enabled.
