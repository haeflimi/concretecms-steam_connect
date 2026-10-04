<?php

namespace SteamConnect\Api;

use RuntimeException;

class SteamApiException extends RuntimeException
{
    /** @var array|null decoded JSON body of the error response, if there was one */
    protected $responseData;

    public function setResponseData(?array $responseData): self
    {
        $this->responseData = $responseData;

        return $this;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * The key is invalid or the request is not allowed: retrying with another user won't help.
     */
    public function isFatal(): bool
    {
        return in_array($this->getCode(), [401, 403, 429], true);
    }
}
