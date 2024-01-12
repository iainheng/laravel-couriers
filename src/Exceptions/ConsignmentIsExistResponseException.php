<?php

namespace Nextbyte\Courier\Exceptions;

class ConsignmentIsExistResponseException extends \Exception
{
    /**
     * @var mixed|null
     */
    protected $responseData;

    public function __construct($message, $responseData = null, $code = 0, \Throwable $previous = null)
    {
        $this->responseData = $responseData;

        parent::__construct($message, $code, $previous);
    }

    public function getResponseData() : array
    {
        return $this->responseData;
    }
}
