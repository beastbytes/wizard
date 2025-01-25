<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Event\Step as StepEvent;

/*
 * Provides a step event handler that calls a method with the name of the current step
 */
trait StepHandlerTrait
{
    public function stepHandler(StepEvent $event): void
    {
        $step = $event
            ->getWizard()
            ->getCurrentStep()
        ;

        if (method_exists($this, $step)) {
            $this->$step($event);
            $event->stopPropagation();
        }
    }
}