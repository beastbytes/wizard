<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard;

use Psr\Http\Message\ResponseInterface;

trait WizardTrait
{
    private Wizard $wizard;

    public function wizard(string $step = ''): ResponseInterface
    {
        return $this
            ->wizard
            ->step($step)
        ;
    }
}
