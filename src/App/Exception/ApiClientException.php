<?php

namespace Ecard\Cms\App\Exception;

class ApiClientException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_APP = 'Missing parameter: app';
    const MSG_INVALID_PARAMETER_APP = 'Invalid parameter: app';
    const MSG_MISSING_PARAMETER_ENDPOINT = 'Missing parameter: HTTP endpoint';
    const MSG_INVALID_PARAMETER_ENDPOINT = 'Invalid parameter: HTTP endpoint';
    const MSG_MISSING_PARAMETER_METHOD = 'Missing parameter: HTTP method';
    const MSG_INVALID_PARAMETER_METHOD = 'Invalid parameter: HTTP method';

    const MSG_CURL_ERROR = 'cURL Request Error (%s): %s when executing %s request to %s';
    const MSG_HTTP_ERROR = 'API error: HTTP %s (%s) when executing %s request to %s';

    /**
     * builds error message for curl error.
     *
     * @param int    $code
     * @param string $message
     * @param string $method
     * @param string $url
     *
     * @return string
     */
    public static function curlError(
        $code = 0,
        $message = null,
        $method = '',
        $url = ''
    ) {
        $exceptionMessage = sprintf(
            self::MSG_CURL_ERROR,
            $code,
            $message,
            $method,
            $url
        );

        return $exceptionMessage;
    }

    /**
     * builds error message for HTTP error.
     *
     * @param int    $code
     * @param string $message
     * @param string $method
     * @param string $url
     *
     * @return string
     */
    public static function httpError(
        $code = 0,
        $message = null,
        $method = '',
        $url = ''
    ) {
        $exceptionMessage = sprintf(
            self::MSG_HTTP_ERROR,
            $code,
            $message,
            $method,
            $url
        );

        return $exceptionMessage;
    }
}
