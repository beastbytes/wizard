<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface WizardInterface
{
    public function getCurrentStep(): ?string;
    public function getData(string $step = null): mixed;
    public function getSteps(): array;
    public function reset(): void;
    public function step(ServerRequestInterface $request): ?ResponseInterface;
    public function withSteps(array $steps): self;
}