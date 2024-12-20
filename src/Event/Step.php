<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Wizard;
use BeastBytes\Wizard\WizardInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @psalm-suppress PropertyNotSetInConstructor */
final class Step extends BaseEvent
{
    private array $branches = [];
    private array $data = [];
    private int|string $goto = Wizard::DIRECTION_FORWARD;

    use ResponseTrait;

    public function __construct(WizardInterface $wizard, private ServerRequestInterface $request)
    {
        parent::__construct($wizard);
    }

    public function getBranches(): array
    {
        return $this->branches;
    }

    public function setBranches(array $branches): void
    {
        $this->branches = $branches;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }

    public function setData(mixed $data): void
    {
        $this->data = $data;
    }

    public function getGoto(): int|string
    {
        return $this->goto;
    }

    public function setGoto(int|string $goto): void
    {
        $this->goto = $goto;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
