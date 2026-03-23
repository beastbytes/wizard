.. _events:

Events
======

.. php:namespace:: BeastBytes\Wizard\Event

Wizard uses events to manage which step is being processed, displaying forms, collecting and providing collected data.

All events implement the `Psr\EventDispatcher\StoppableEventInterface`

All event handlers receive the Wizard instance; they are typically declared in the controller

The Wizard raises the following events:

.. toctree::
    :maxdepth: 2
    :glob:

    events/*
