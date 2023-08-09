<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Wizard;
use Psr\EventDispatcher\StoppableEventInterface;

trait WizardTrait
{
    private bool $continue = true;

    public function __construct(private Wizard $wizard)
    {
    }

    public function continue(bool $continue): self
    {
        $this->continue = $continue;
        return $this;
    }

    public function getWizard(): Wizard
    {
        return $this->wizard;
    }

    public function shouldContinue(): bool
    {
        return $this->continue;
    }
}
