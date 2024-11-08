<?php
/**
 * @copyright Copyright © 2024 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\CurrentRoute;

trait WizardTrait
{
    /**
     * @throws \BeastBytes\Wizard\Exception\InvalidConfigException
     */
    public function wizard(ServerRequestInterface $request): ResponseInterface
    {
        return $this
            ->wizard
            ->step($request)
        ;
    }
}
