Step Event
==========

.. php:namespace:: BeastBytes\Wizard\Event

.. php:class:: Step

    The Step event is raised at least twice for every step in Wizard

    It is initially raised on the step's `HTTP GET` request;
    the event handler should render the form for the current step

    It is then raised on the step's `HTTP POST` request when the form is submitted;
    the event handler should validate the form and either save the form data or re-render the form,
    depending on the validation result

    The Step event *must* be handled

    .. php:method:: getBranches()

        Internal

        :returns: The branches array
        :rtype: array

    .. php:method:: setBranches(array $branches)

        Enable/disable branches as required

        :param array{string, bool} $branches: Branch names and whether the branch is enabled or disabled
        :rtype: void

    .. php:method:: getData()

        Internal

        :returns: Data saved in the event
        :rtype: mixed

    .. php:method:: hasData()

        Internal

        :returns: `true` if the event has data set, `false` if not
        :rtype: bool

    .. php:method:: setData(mixed $data)

        Set the data for the event

        .. note::
            If the event is handling a repeated step set the data as normal,
            Wizard will automatically save data for the repetitions in an array for the step

        :param mixed $data: Data to be saved by Wizard
        :rtype: void

    .. php:method:: getGoto()

        Internal

        :returns:  The goto value. See :php:meth:`Step::setGoto`
        :rtype: int|string

    .. php:method:: setGoto(int|string $goto)

        Set the step that Wizard will go to after processing the current step

        `$goto` can be:

        * the name of a step in the steps array
        * Wizard::DIRECTION_BACKWARD - Wizard will go back one step
        * Wizard::DIRECTION_FORWARD - Wizard will go the next step (default). Which step is the next step is determined by the current step and the auto advance setting
        * Wizard::DIRECTION_REPEAT - Will repeat the current step

        :param int|string $goto: The goto value
        :rtype: void

    .. php:method:: getRequest()

        The request can be used to the determine the HTTP method

        :returns: The HTTP request for the step
        :rtype: Psr\Http\Message\ServerRequestInterface

.. |event| replace:: Step
.. include:: /snippets/responseTrait.rst
.. include:: /snippets/baseEvent.rst

Step State Diagram
------------------
.. mermaid::

    stateDiagram-v2
    state xxx <<choice>>
    state StepEventHandler {
        [*] --> xxx
        xxx --> RenderForm: GET Request
        xxx --> ManageData: POST Request
        RenderForm --> [*]: Form submitted
        ManageData --> [*]
    }
    [*] --> Action
    Action --> Wizard: step()
    Wizard --> StepEvent
    StepEvent --> StepEventHandler
    StepEventHandler --> Action
