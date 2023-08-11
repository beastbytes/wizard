<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

trait StepDataTrait
{
    private mixed $data = null;

    public function getData(): mixed
    {
        return $this->data;
    }

    public function hasData(): bool
    {
        return !empty($this->data);
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }
}
