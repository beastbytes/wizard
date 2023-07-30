<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Tests\Support\EventHandlers;

use BeastBytes\Wizard\Event\Step as StepEvent;
use Yiisoft\Session\Session;

final class Step
{
    private const DATA_KEY = 'testData';

    public function handle(StepEvent $event): void
    {
        $session = new Session();

        if ($session->has(self::DATA_KEY)) {
            $event->setData($session->get(self::DATA_KEY));
            $session->remove(self::DATA_KEY);
        } else {
            $session->set(self::DATA_KEY, ['key' => 'value']);
        }
    }
}
