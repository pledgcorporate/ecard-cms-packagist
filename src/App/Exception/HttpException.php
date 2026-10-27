<?php

namespace Ecard\Cms\App\Exception;

class HttpException extends EcardCmsException
{
    const MSG_INVALID_PARAMETER_PROXY = 'Invalid parameter: proxy (ip address or cidr range expected)';
    const MSG_INVALID_PARAMETER_TRUSTED_HOST = 'Invalid parameter: trusted host (host like "shop.example.com" or "*.example.com" expected, without scheme, port nor path)';
    const MSG_CANNOT_GENERATE_RANDOM_FILE_NAME = 'Cannot generate a random file name (no secure source of randomness)';
    const MSG_INVALID_PARAMETER_RAW_BODY_CALLBACK = 'Invalid parameter: raw body callback (Closure or null expected)';
    const MSG_CANNOT_READ_RAW_BODY = 'Cannot read the raw body of the request';
    const MSG_MISSING_PARAMETER_DESTINATION = 'Missing parameter: destination';
    const MSG_MISSING_PARAMETER_DIRECTORY = 'Missing parameter: directory';
    const MSG_INVALID_PARAMETER_ALLOWED_TYPES = 'Invalid parameter: allowed types (non empty list of mime types expected)';
    const MSG_INVALID_PARAMETER_MAX_SIZE = 'Invalid parameter: max size (positive integer, in bytes, expected)';
    const MSG_UPLOADED_FILE_NOT_OK = 'The uploaded file has an error and cannot be used';
    const MSG_UPLOADED_FILE_NOT_UPLOADED = 'The file was not uploaded by the client: it can neither be read nor moved';
    const MSG_UPLOADED_FILE_TOO_LARGE = 'The uploaded file is too large';
    const MSG_UPLOADED_FILE_TYPE_NOT_ALLOWED = 'The type of the uploaded file is not allowed';
    const MSG_CANNOT_CREATE_DIRECTORY = 'Cannot create the destination directory of the uploaded file';
    const MSG_CANNOT_MOVE_UPLOADED_FILE = 'Cannot move the uploaded file';
    const MSG_CANNOT_READ_UPLOADED_FILE = 'Cannot read the uploaded file';

    // response:
    const MSG_INVALID_PARAMETER_HEADER_SENDER = 'Invalid parameter: header sender (HeaderSender or null expected)';
    const MSG_INVALID_PARAMETER_CODE = 'Invalid parameter: status code (integer from 100 to 599 expected)';
    const MSG_INVALID_PARAMETER_HEADER_NAME = 'Invalid parameter: header name (http token like "X-Request-Id" expected)';
    const MSG_INVALID_PARAMETER_HEADER_VALUE = 'Invalid parameter: header value (string or number without control characters expected)';
    const MSG_INVALID_PARAMETER_CONTENT_TYPE = 'Invalid parameter: content type (mime type like "text/html" expected)';
    const MSG_INVALID_PARAMETER_CHARSET = 'Invalid parameter: charset (token like "utf-8" expected)';
    const MSG_INVALID_PARAMETER_REDIRECT_CODE = 'Invalid parameter: redirect code (301, 302, 303, 307 or 308 expected)';
    const MSG_INVALID_PARAMETER_REDIRECT_URL = 'Invalid parameter: redirect url (http or https url, or relative url, made of printable ascii characters except "\\" expected)';
    const MSG_UNALLOWED_EXTERNAL_REDIRECT = 'Unallowed redirect to another host (see the $allowExternal parameter)';
    const MSG_INVALID_PARAMETER_EXPIRATION = 'Invalid parameter: expiration (null, positive number of seconds or DateTimeInterface expected)';
    const MSG_INVALID_PARAMETER_COOKIE_NAME = 'Invalid parameter: cookie name (http token expected)';
    const MSG_INVALID_PARAMETER_COOKIE_VALUE = 'Invalid parameter: cookie value (string or number expected)';
    const MSG_INVALID_PARAMETER_COOKIE_PATH = 'Invalid parameter: cookie path (path starting with "/", made of printable ascii characters except ";", expected)';
    const MSG_INVALID_PARAMETER_COOKIE_DOMAIN = 'Invalid parameter: cookie domain (domain like "example.com" expected, without scheme, port nor path)';
    const MSG_INVALID_PARAMETER_SAME_SITE = 'Invalid parameter: same site (Strict, Lax, None or null expected)';
    const MSG_INSECURE_COOKIE = 'Cannot send the cookie: SameSite=None and the "__Secure-" and "__Host-" name prefixes require a secure cookie';
    const MSG_INVALID_HOST_COOKIE = 'Cannot send the cookie: the "__Host-" name prefix requires the "/" path and no domain';
    const MSG_INVALID_PARAMETER_DOWNLOAD_NAME = 'Invalid parameter: download name (string or null expected)';
    const MSG_CANNOT_READ_FILE = 'Cannot read the file to send';

    const MSG_CANNOT_ENCODE_JSON = 'Cannot encode the json response: %s';
    const MSG_HEADERS_ALREADY_SENT = 'Cannot modify the http response: the headers have already been sent (output started at %s:%s)';

    /**
     * builds the error message of a json encoding error.
     *
     * @param string $message the message of json_last_error_msg()
     *
     * @return string
     */
    public static function jsonEncodingError(
        $message = ''
    ) {
        return sprintf(
            self::MSG_CANNOT_ENCODE_JSON,
            $message
        );
    }

    /**
     * builds the error message of a header sent after the output has started.
     *
     * @param string $file the file where the output has started
     * @param int    $line the line where the output has started
     *
     * @return string
     */
    public static function headersAlreadySent(
        $file = '',
        $line = 0
    ) {
        return sprintf(
            self::MSG_HEADERS_ALREADY_SENT,
            $file,
            $line
        );
    }
}
