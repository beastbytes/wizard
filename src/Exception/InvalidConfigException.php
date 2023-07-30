<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\Wizard\Exception;

class InvalidConfigException extends \Exception
{
    public function __construct(string $message, public array|null $errorInfo = [], \Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return string Readable representation of exception.
     */
    public function __toString(): string
    {
        return parent::__toString() . PHP_EOL . 'Additional Information:' . PHP_EOL . print_r($this->errorInfo, true);
    }
}
