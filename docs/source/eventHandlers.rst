.. _event_handlers:
Event Handlers
--------------

Wizard is event driven. This section describes what each of the event handlers should do.

All events implement the `Psr\EventDispatcher\StoppableEventInterface`;
this means that all event handlers can stop Wizard.

AfterWizard
+++++++++++

Raised after Wizard ends - i.e. when there are no further steps to be processed or Wizard has been stopped.

Handling the `AfterWizard` event is required.

The event handler is responsible for persisting or otherwise handling the data if Wizard completed successfully,
or taking that appropriate action if there was an error.

The event handler *must* generate a response (`Psr\Http\Message\ResponseInterface`)
and set the response in the event by calling `AfterWizard::setResponse($response)`.

BeforeWizard
++++++++++++

Raised before Wizard starts - i.e. before any steps have been processed.

Handling the `BeforeWizard` event is optional.

The application can prevent Wizard from running by calling :php:meth:`BeforeWizard::stopWizard()`.

Step
++++

The `Step` event is the *workhorse* of Wizard. It is raised at least twice for each step;
the first time is during the `GET` request, then again for `POST` requests.

In many ways, the `Step` event handler is similar to an action. It is often a good idea to for the even handler
to call step specific handlers, particularly if there are many steps. The package includes `StepHandlerTrait` that
can be used as the `Step` event handler;
it calls a method in the same class that it is used in that has the same name as the current step.

GET
===

The event handler should render the form for the current step
and set the response in the event by calling `Step::setResponse($response)`.

POST
====

The event handler is responsible for validating the submitted form data.

If validation is successful the event should save the data to the event (see :php:meth:`Step::setData`)
and if necessary enable/disable branches (see :php:meth:`Step::setBranches`)
or set the step to goto (see :php:meth:`setGoto`), then return.

.. note::

    Do not set the response in the event. Wizard will generate the appropriate response.

If validation fails the event handler should re-render the form to allow the user to correct the error(s)
and set the response in the event.

StepTimeOut
+++++++++++

Raised if there is a step timeout and a step is not processed within the timeout.

The event handler is responsible for deciding what to do in the event of a timeout.

The event handler *must* generate a response (`Psr\Http\Message\ResponseInterface`)
and set the response in the event by calling `StepTimeOut::setResponse($response)`.
