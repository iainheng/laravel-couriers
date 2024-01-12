<?php

namespace Nextbyte\Courier\Exceptions;

class ClientResponseException extends \InvalidArgumentException
{
    /**
     * @var string|null
     */
    protected $description = null;

    /**
     * @var mixed|null
     */
    protected $responseData = null;

    public function __construct($message, $description = '', $responseData = null, $code = 0, \Throwable $previous = null)
    {
        $this->description = $description;
        $this->responseData = $responseData;

        parent::__construct($message . ' ' . $description, $code, $previous);
    }

    public function getDescription() : string
    {
        return $this->description;
    }

    public function getResponseData() : array
    {
        return $this->responseData;
    }
}
