<?php

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

/** @psalm-suppress PropertyNotSetInConstructor */
final class AfterWizard extends BaseEvent
{
    use ResponseTrait;
}