Wizard Class
============

.. php:namespace:: BeastBytes\Wizard

.. php:class:: Wizard

    Implements :php:class:`WizardInterface`

    .. php:const:: AUTO_ADVANCE (bool = true)

        Wizard will go to the first unprocessed step when going forward in the steps array

    .. php:const:: BRANCH_DISABLED (int = -1)

        Disable a branch

    .. php:const:: BRANCH_ENABLED (int = 1)

        Enable a branch

    .. php:const:: DIRECTION_BACKWARD (int = -1)

        Go one step backward in the steps array to get the next step

    .. php:const:: DIRECTION_FORWARD (int = 1)

        Go forward in the steps array to get the next step

    .. php:const:: DIRECTION_REPEAT (int = 0)

        Repeat the current step

    .. php:const:: DEFAULT_BRANCH (bool = true)

        Use the default (first) branch

    .. php:const:: FORWARD_ONLY (bool = true)

        Wizard only goes forward in the steps array

    .. php:method:: getCurrentStep()

        :returns: The name of the current step
        :rtype: string

    .. php:method:: getData(?string $step = null)

        Get the data for a step or all processed steps

        :param ?string $step: The name of the step (default: all processed steps)
        :returns: Data for the specified step or a map containing data for all processed steps indexed by step name
        :rtype: mixed

    .. php:method:: getSteps()

        Returns the wizard's active steps; active steps are those that either have been processed - have data -
        and are to be processed

        The active steps may change as steps are processed due to Plot Branching

        :returns: The wizard's active steps
        :rtype: array

    .. php:method:: pause()

        :returns: Serialisation of the Wizard instance that can be used with :php:meth:`resume()`
        :rtype: string

    .. php:method:: resume(string $data)

        Resumes a Wizard instance

        :param string $data: A serialised Wizard instance from :php:meth:`pause()`
        :returns: An instance of Wizard created from the data. The instance will have the steps, data, and settings of the paused Wizard
        :rtype: WizardInterface

    .. php:method:: reset()

        Resets the Wizard instance

    .. php:method:: step(Psr\Http\Message\ServerRequestInterface $request)

        Processes a Wizard step

        :param Psr\Http\Message\ServerRequestInterface $request: An HTTP request instance
        :returns: HTTP response
        :rtype: Psr\Http\Message\ResponseInterface

    .. php:method:: withAutoAdvance(bool $autoAdvance)

        Whether the Wizard auto-advances to the first unprocessed step or the next step in the steps array

        :param bool $autoAdvance: `Wizard::AUTO_ADVANCE` (default) to auto-advance to the first unprocessed step, or `!Wizard::AUTO_ADVANCE` to go to the next step
        :returns: A new instance of `Wizard` with auto-advance configured
        :rtype: Wizard

    .. php:method:: withDefaultBranch(bool $defaultBranch)

        Whether the default (first) branch in a group is used by default or the group is skipped unless a branch is enabled

        :param bool $defaultBranch: `Wizard::DEFAULT_BRANCH` (default) if Wizard should use the first branch of a branch group unless another branch is enabled, or `!Wizard::DEFAULT_BRANCH` if Wizard should skip a branch group unless a branch is enabled
        :returns: A new instance of `Wizard` with default branch configured
        :rtype: Wizard

    .. php:method:: withEvents(array $events)

        Set the events and their handlers for the Wizard

        See :ref:_events for a full description of Wizard events

        :param array{string, callable} $events: An array whose keys are the FQCN of Wizard events and the values are the handlers
        :returns: A new instance of `Wizard` with the events
        :rtype: Wizard

    .. php:method:: withForwardOnly(bool $forwardOnly)

        Whether Wizard must move to the next valid step or can return to a previously processed step

        :param bool $forwardOnly: `Wizard::FORWARD_ONLY` if Wizard should move forwards through the steps array, or `!Wizard::FORWARD_ONLY` (default) if Wizard can return to a previously processed step
        :returns: A new instance of `Wizard` with forward only configured
        :rtype: Wizard

    .. php:method:: withSessionKey(string $sessionKey)

        Set the session key for the Wizard instance

        Use if the default session key, `__wizard`, is used by another package

        :param string $sessionKey: The session key
        :returns: A new instance of `Wizard` with the session key
        :rtype: Wizard

    .. php:method:: withSteps(array $steps)

        Set the steps for the Wizard

        :param array $steps: Steps for the Wizard
        :returns: A new instance of `Wizard` with the steps
        :rtype: Wizard

    .. php:method:: withStepParameter(string $stepParameter)

        Sets the route step parameter

        The default no step parameter

        The step parameter provides a unique URL for each step that will be displayed in the browser address bar
        thereby providing visual information to the user; it is not used internally.

        .. note::

            There are other, better, more accessible, ways of providing information about progress through the wizard
            to the user, e.g. a stepper component.

        :param string $step: The step parameter
        :returns: A new instance of `Wizard` with the step parameter
        :rtype: Wizard

    .. php:method:: withStepTimeout(int $stepTimeout)

        Sets a step timeout in seconds (0 = no timeout)

        If set and a step is not completed within the timeout Wizard raises the `StepExpired` event

        By default there is no step timeout

        :psram int $stepTimeout: Step timeout in seconds
        :returns: A new instance of `Wizard` with step timeout configured
        :rtype: Wizard
