<?php

namespace WHMCS\Module\Server\Evorxa\Api;

/**
 * Error raised for any failed Evorxa API call.
 *
 * status 0 means the request never got an HTTP answer (DNS, TLS, timeout).
 */
class ApiException extends \Exception
{
    private $status;
    private $body;

    public function __construct($message, $status = 0, $body = null)
    {
        parent::__construct((string) $message, (int) $status);
        $this->status = (int) $status;
        $this->body = $body;
    }

    public function status()
    {
        return $this->status;
    }

    public function body()
    {
        return $this->body;
    }

    public function isNetwork()
    {
        return $this->status === 0;
    }

    /**
     * True when Evorxa definitely rejected the request, so nothing was created or charged.
     * Network errors, timeouts, rate limits and 5xx are ambiguous.
     */
    public function isDefinite()
    {
        return $this->status >= 400 && $this->status < 500 && !in_array($this->status, [408, 429], true);
    }

    /** Upstream message, including the first validation error when present. */
    public function upstreamMessage()
    {
        $body = is_array($this->body) ? $this->body : [];
        $msg = '';
        foreach (['error', 'message'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                $msg = $body[$key];
                break;
            }
        }
        if (!empty($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $errors) {
                $first = is_array($errors) ? reset($errors) : $errors;
                if (is_string($first) && $first !== '') {
                    $msg = trim($msg . ' ' . $first);
                    break;
                }
            }
        }
        return $msg !== '' ? $msg : $this->getMessage();
    }

    /** Admin-facing explanation. Never shown to clients verbatim. */
    public function friendly()
    {
        switch (true) {
            case $this->status === 0:
                return 'Could not reach the Evorxa API (' . $this->getMessage() . ').';
            case $this->status === 401:
                return 'Evorxa rejected the API token. Check the token on the Evorxa server (Setup > Servers).';
            case $this->status === 402:
                return 'Insufficient Evorxa wallet balance. Top up your Evorxa wallet and retry.';
            case $this->status === 403:
                return 'The Evorxa API token is not allowed to do this (missing scope or not a project owner): ' . $this->upstreamMessage();
            case $this->status === 404:
                return 'Not found on Evorxa.';
            case $this->status === 422:
                return 'Evorxa refused the request: ' . $this->upstreamMessage();
            case $this->status === 429:
                return 'Evorxa rate limit reached. Try again in a minute.';
            case $this->status >= 500:
                return 'Evorxa returned a server error (HTTP ' . $this->status . '). Try again shortly.';
            default:
                return 'Evorxa error (HTTP ' . $this->status . '): ' . $this->upstreamMessage();
        }
    }
}
