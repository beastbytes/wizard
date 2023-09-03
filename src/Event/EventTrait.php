<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Wizard;

trait EventTrait
{
    private bool $stopPropagation = false;

    public function __construct(private Wizard $wizard)
    {
    }

    public function getWizard(): Wizard
    {
        return $this->wizard;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopPropagation;
    }

    public function stopPropagation(): void
    {
        $this->stopPropagation = true;
    }
}
