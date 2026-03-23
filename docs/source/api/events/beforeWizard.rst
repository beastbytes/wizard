BeforeWizard Event
==================

.. php:namespace:: BeastBytes\Wizard\Event

.. php:class:: BeforeWizard

    Raised the first time :php:meth:`Wizard::step()` is called

    To prevent Wizard from running, call :php:meth:`BeforeWizard::stopWizard()` in the event handler

    Handling the BeforeWizard event is optional

.. include:: /snippets/baseEvent.rst

BeforeWizard State Diagram
--------------------------
.. mermaid::

    stateDiagram-v2
    state stopWizard <<choice>>
    state first <<choice>>
    [*] --> Action: HTTP Request
    Action --> Wizard: step()
    Wizard --> first
    first --> BeforeWizard: Wizard not started
    first --> ProcessSteps: Wizard started
    BeforeWizard --> BeforeWizardEventHandler
    BeforeWizardEventHandler --> stopWizard
    stopWizard --> AfterWizard: stopWizard() called
    AfterWizard --> AfterWizardEventHandler
    stopWizard --> ProcessSteps
