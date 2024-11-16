<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

/** @psalm-suppress PropertyNotSetInConstructor */
final class StepExpired extends BaseEvent
{
    use ResponseTrait;
}
