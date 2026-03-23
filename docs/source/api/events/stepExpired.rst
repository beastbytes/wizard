.. _stepexpired:

StepExpired Event
=================

.. php:namespace:: BeastBytes\Wizard\Event

.. php:class:: StepExpired

    If Wizard is configured with a step timeout, the StepExpired event is raised if a step times out

    The StepExpired event *must* be handled if a step timeout is set

.. |event| replace:: StepExpired
.. include:: /snippets/responseTrait.rst
.. include:: /snippets/baseEvent.rst

StepExpired State Diagram
-------------------------
.. mermaid::

    stateDiagram-v2
    state xxx <<choice>>
    [*] --> Action: HTTP Request
    Action --> Wizard: step()
    Wizard --> xxx
    xxx --> RenderForm: GET Request
    xxx --> StepExpired: POST Request & step timeout expired
    RenderForm --> Action: Form submitted
    StepExpired --> StepExpiredEventHandler
