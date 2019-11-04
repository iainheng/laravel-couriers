<?php
/**
 * Abstract Response
 */

namespace Nextbyte\Courier\Messages;

use Symfony\Component\HttpFoundation\RedirectResponse as HttpRedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 *
 */
abstract class AbstractTrackingResponse
{

    /**
     * The data contained in the response.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Constructor
     *
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Is the response successful?
     *
     * @return boolean
     */
    public function isPending()
    {
        return false;
    }

    /**
     * Does the response require a redirect?
     *
     * @return boolean
     */
    public function isRedirect()
    {
        return true;
    }

    /**
     * Is the response a transparent redirect?
     *
     * @return boolean
     */
    public function isTransparentRedirect()
    {
        return false;
    }

    /**
     * Get the response data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Gets the redirect target url.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return null;
    }

    /**
     * Get the required redirect method (either GET or POST).
     *
     * @return string
     */
    public function getRedirectMethod()
    {
        return 'GET';
    }

    /**
     * Gets the redirect form data array, if the redirect method is POST.
     *
     * @return array
     */
    public function getRedirectData()
    {
        return [];
    }

    /**
     * Automatically perform any required redirect
     *
     * This method is meant to be a helper for simple scenarios. If you want to customize the
     * redirection page, just call the getRedirectUrl() and getRedirectData() methods directly.
     *
     * @return void
     */
    public function redirect()
    {
        $this->getRedirectResponse()->send();
    }

    /**
     * @return HttpRedirectResponse|HttpResponse
     */
    public function getRedirectResponse()
    {
        $this->validateRedirect();

        if ('GET' === $this->getRedirectMethod()) {
            return HttpRedirectResponse::create($this->getRedirectUrl());
        }

        $hiddenFields = '';
        foreach ($this->getRedirectData() as $key => $value) {
            $hiddenFields .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                htmlentities($key, ENT_QUOTES, 'UTF-8', false),
                htmlentities($value, ENT_QUOTES, 'UTF-8', false)
            )."\n";
        }

        $output = '<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit();">
    <form action="%1$s" method="post">
        <p>Redirecting to payment page...</p>
        <p>
            %2$s
            <input type="submit" value="Continue" />
        </p>
    </form>
</body>
</html>';
        $output = sprintf(
            $output,
            htmlentities($this->getRedirectUrl(), ENT_QUOTES, 'UTF-8', false),
            $hiddenFields
        );

        return HttpResponse::create($output);
    }

    /**
     * Validate that the current Response is a valid redirect.
     *
     * @return void
     */
    protected function validateRedirect()
    {
        if (!$this->isRedirect()) {
            throw new \RuntimeException('This response does not support redirection.');
        }

        if (empty($this->getRedirectUrl())) {
            throw new \RuntimeException('The given redirectUrl cannot be empty.');
        }

        if (!in_array($this->getRedirectMethod(), ['GET', 'POST'])) {
            throw new \RuntimeException('Invalid redirect method "'.$this->getRedirectMethod().'".');
        }
    }
}
