<?php

namespace Nextbyte\Courier;

class ConsignmentFile
{
    protected $name;
    protected $extension;
    protected $body;
    protected $response;

    protected function __construct(array $attributes)
    {
        foreach ($attributes as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    public static function create(array $attributes): ConsignmentFile
    {
        return new static($attributes);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
