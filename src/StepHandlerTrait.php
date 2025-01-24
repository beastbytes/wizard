<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard;

use BeastBytes\Wizard\Event\Step as StepEvent;

/*
 * Use this trait when:
 * * the step handler methods are in the class using this trait
 * * the step handler method names are the same as the step names
 */
trait StepHandlerTrait
{
    /* Step event handler */
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