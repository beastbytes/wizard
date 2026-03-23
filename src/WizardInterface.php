<?php

declare(strict_types=1);

namespace BeastBytes\Wizard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface WizardInterface
{
    public function getCurrentStep(): ?string;
    public function getData(?string $step = null): mixed;
    public function getSteps(): array;
    public function step(ServerRequestInterface $request): ?ResponseInterface;
    public function withSteps(array $steps): self;
}