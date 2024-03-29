<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Wizard;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Step implements StoppableEventInterface
{
    use StepDataTrait;
    use EventTrait;

    private array $branches = [];
    private int|string $goto = Wizard::DIRECTION_FORWARD;

    public function __construct(private Wizard $wizard, private ServerRequestInterface $request)
    {
    }

    public function getBranches(): array
    {
        return $this->branches;
    }

    public function getGoto(): int|string
    {
        return $this->goto;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setBranches(array $branches): void
    {
        $this->branches = $branches;
    }

    public function setGoto(int|string $goto): void
    {
        $this->goto = $goto;
    }
}
