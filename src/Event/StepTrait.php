<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

trait StepTrait
{
    private array $data = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function hasData(): bool
    {
        return !empty($this->data);
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
