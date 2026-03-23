StepHandlerTrait
----------------

.. php:namespace:: BeastBytes\Wizard\Event

.. php:trait:: StepHandlerTrait

    StepHandlerTrait provides a `Step` event handler
    that calls a method in the same class with the same name as the current step;
    the event instance is passed to the method

    .. php:trait:: stepHandler(Step $event)

        Calls a method in the same class with the same name as the current step;
        the event instance is passed to the method

        :param Step $event: A :php:class:`Step` event
        :rtype: void
