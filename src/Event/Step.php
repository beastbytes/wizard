<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use BeastBytes\Wizard\Wizard;
use Psr\Http\Message\ServerRequestInterface;

final class Step
{
    use StepTrait;
    use WizardTrait;

    private int|string $goto = Wizard::DIRECTION_FORWARD;

    public function __construct(private Wizard $wizard, private ServerRequestInterface $request)
    {
    }

    public function getGoto(): int|string
    {
        return $this->goto;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setGoto(int|string $goto): void
    {
        $this->ngoto = $goto;
    }
}
