Wizard
======

Wizard simplifies multi-part form handling in the `Yii3 PHP framework <https://www.yiiframework.com/>`__.

Wizard allows a single action to process all steps of a multi-part form,
storing form data in the session as it progresses
and making available to event handlers to use as required by the application.

Features
--------

* Event driven - Wizard events tell the application what has happened, the application decides what to do about it
* All forms submit to the same action
* Allow returning to earlier steps or enforce forward only navigation
* Looping - repeat one or more steps as many times as needed
* Plot Branching Navigation (PBN) - decide which path to take depending on the user's response
* Step Timeout - steps may have a timeout to ensure a user responds within a given time
* Pause/resume - save partially completed Wizards; restore and continue from that point

.. toctree::
    :maxdepth: 2

    installation
    usage
    eventHandlers
    plotBranchingNavigation
    api
