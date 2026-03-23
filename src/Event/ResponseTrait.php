<?php

declare(strict_types=1);

namespace BeastBytes\Wizard\Event;

use Psr\Http\Message\ResponseInterface;

trait ResponseTrait
{
    private ?ResponseInterface $response = null;

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function setResponse(ResponseInterface $response): self
    {
        $this->response = $response;
        return $this;
    }
}