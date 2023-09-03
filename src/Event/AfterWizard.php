<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use Psr\EventDispatcher\StoppableEventInterface;

final class AfterWizard implements StoppableEventInterface
{
    use StepDataTrait;
    use EventTrait;
}
