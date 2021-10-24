<?php
/**
 * Redirect Response interface
 */

namespace Nextbyte\Courier\Messages;

/**
 * Redirect Response interface
 *
 * This interface class defines the functionality of a response
 * that is a redirect response.
 *
 * @see ResponseInterface
 */
interface RedirectResponseInterface
{
    /**
     * Does the response require a redirect?
     *
     * @return boolean
     */
    public function isRedirect();

    /**
     * Gets the redirect target url.
     *
     * @return string
     */
    public function getRedirectUrl();

    /**
     * Get the required redirect method (either GET or POST).
     *
     * @return string
     */
    public function getRedirectMethod();

    /**
     * Gets the redirect form data array, if the redirect method is POST.
     *
     * @return array
     */
    public function getRedirectData();

    /**
     * Perform the required redirect.
     *
     * @return void
     */
    public function redirect();
}
