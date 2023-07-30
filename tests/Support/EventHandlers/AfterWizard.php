<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Tests\Support\EventHandlers;

use BeastBytes\Wizard\Event\AfterWizard as AfterWizardEvent;

final class AfterWizard
{
    public function handle(AfterWizardEvent $event): void
    {
    }
}
