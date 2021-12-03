<?php

namespace Nextbyte\Courier\Exceptions;

class MissingRequiredParamException extends \InvalidArgumentException
{
    protected $params = [];

    public function __construct($requiredParam, $params = [], \Throwable $previous = null)
    {
        $message = sprintf('The param "%s" is required but missing.', $requiredParam);

        $this->params = $params;

        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
