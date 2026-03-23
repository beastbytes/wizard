AfterWizard Event
=================

.. php:namespace:: BeastBytes\Wizard\Event

.. php:class:: AfterWizard

    The AfterWizard is raised when Wizard ends

    Wizard will end if it has completed all the steps or an event handler has stopped it

    The AfterWizard event *must* be handled

.. |event| replace:: AfterWizard
.. include:: /snippets/responseTrait.rst
.. include:: /snippets/baseEvent.rst

AfterWizard State Diagram
-------------------------
.. mermaid::

    stateDiagram-v2
    state end <<choice>>
    state xxx <<choice>>
    [*] --> Action: HTTP Request
    Action --> Wizard: step()
    Wizard --> end
    end --> xxx: Steps to process
    xxx --> RenderForm: GET Request
    xxx --> ManageData: POST Request
    RenderForm --> Action: Form submitted
    ManageData --> Action
    end --> AfterWizard: All steps processed
    AfterWizard --> AfterWizardEventHandler
