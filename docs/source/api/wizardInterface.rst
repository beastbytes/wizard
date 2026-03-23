Wizard Interface
================

.. php:namespace:: BeastBytes\Wizard

.. php:class:: WizardInterface

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

    .. php:method:: step(Psr\Http\Message\ServerRequestInterface $request)

        Processes a Wizard step

        :returns: HTTP response
        :rtype: Psr\Http\Message\ResponseInterface

    .. php:method:: withSteps(array $steps)

        Set the steps for the Wizard

        :param array $steps: Steps for the Wizard
        :returns: A new instance of `Wizard` with the steps
        :rtype: Wizard

    .. php:method:: withStepParameter(string $stepParameter)

        Sets the step parameter as defined in the Route

        The default step parameter is **WHAT**

        :param string $step: The step parameter
        :returns: A new instance of `Wizard` with the step parameter
        :rtype: Wizard
