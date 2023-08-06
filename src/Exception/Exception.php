<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Exception;

use Yiisoft\FriendlyException\FriendlyExceptionInterface;

class Exception extends \Exception implements FriendlyExceptionInterface
{
    public function __construct(string $message, public string $solution = '', Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getName(): string
    {
        return $this->getMessage();
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }
}
