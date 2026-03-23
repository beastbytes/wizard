Inherited from BaseEvent
------------------------

    .. php:method:: getWizard()

        :returns: The Wizard instance that raised the event
        :rtype: WizardInterface

    .. php:method:: isPropagationStopped()

        :returns: `true` if event propagation has been stopped, `false` if not
        :rtype: bool

    .. php:method:: stopPropagation()

        Stop event propagation

        :rtype: void

    .. php:method:: isWizardStopped()

        :returns: `true` if the wizard has been stopped, `false` if not
        :rtype: bool

    .. php:method:: stopWizard()

        Stop the Wizard

        :rtype: void
