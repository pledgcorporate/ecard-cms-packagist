<?php

namespace Ecard\Cms\App\Component\Constants;

use Ecard\Cms\Dependencies\Fig\Http\Message\RequestMethodInterface;
use Ecard\Cms\Dependencies\Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use LogicException;

/**
 * @property string $METHOD_HEAD
 * @property string $METHOD_GET
 * @property string $METHOD_POST
 * @property string $METHOD_PUT
 * @property string $METHOD_PATCH
 * @property string $METHOD_DELETE
 * @property string $METHOD_PURGE
 * @property string $METHOD_OPTIONS
 * @property string $METHOD_TRACE
 * @property string $METHOD_CONNECT
 * @property int    $STATUS_CONTINUE
 * @property int    $STATUS_SWITCHING_PROTOCOLS
 * @property int    $STATUS_PROCESSING
 * @property int    $STATUS_EARLY_HINTS
 * @property int    $STATUS_OK
 * @property int    $STATUS_CREATED
 * @property int    $STATUS_ACCEPTED
 * @property int    $STATUS_NON_AUTHORITATIVE_INFORMATION
 * @property int    $STATUS_NO_CONTENT
 * @property int    $STATUS_RESET_CONTENT
 * @property int    $STATUS_PARTIAL_CONTENT
 * @property int    $STATUS_MULTI_STATUS
 * @property int    $STATUS_ALREADY_REPORTED
 * @property int    $STATUS_IM_USED
 * @property int    $STATUS_MULTIPLE_CHOICES
 * @property int    $STATUS_MOVED_PERMANENTLY
 * @property int    $STATUS_FOUND
 * @property int    $STATUS_SEE_OTHER
 * @property int    $STATUS_NOT_MODIFIED
 * @property int    $STATUS_USE_PROXY
 * @property int    $STATUS_RESERVED
 * @property int    $STATUS_TEMPORARY_REDIRECT
 * @property int    $STATUS_PERMANENT_REDIRECT
 * @property int    $STATUS_BAD_REQUEST
 * @property int    $STATUS_UNAUTHORIZED
 * @property int    $STATUS_PAYMENT_REQUIRED
 * @property int    $STATUS_FORBIDDEN
 * @property int    $STATUS_NOT_FOUND
 * @property int    $STATUS_METHOD_NOT_ALLOWED
 * @property int    $STATUS_NOT_ACCEPTABLE
 * @property int    $STATUS_PROXY_AUTHENTICATION_REQUIRED
 * @property int    $STATUS_REQUEST_TIMEOUT
 * @property int    $STATUS_CONFLICT
 * @property int    $STATUS_GONE
 * @property int    $STATUS_LENGTH_REQUIRED
 * @property int    $STATUS_PRECONDITION_FAILED
 * @property int    $STATUS_PAYLOAD_TOO_LARGE
 * @property int    $STATUS_URI_TOO_LONG
 * @property int    $STATUS_UNSUPPORTED_MEDIA_TYPE
 * @property int    $STATUS_RANGE_NOT_SATISFIABLE
 * @property int    $STATUS_EXPECTATION_FAILED
 * @property int    $STATUS_IM_A_TEAPOT
 * @property int    $STATUS_MISDIRECTED_REQUEST
 * @property int    $STATUS_UNPROCESSABLE_ENTITY
 * @property int    $STATUS_LOCKED
 * @property int    $STATUS_FAILED_DEPENDENCY
 * @property int    $STATUS_TOO_EARLY
 * @property int    $STATUS_UPGRADE_REQUIRED
 * @property int    $STATUS_PRECONDITION_REQUIRED
 * @property int    $STATUS_TOO_MANY_REQUESTS
 * @property int    $STATUS_REQUEST_HEADER_FIELDS_TOO_LARGE
 * @property int    $STATUS_UNAVAILABLE_FOR_LEGAL_REASONS
 * @property int    $STATUS_INTERNAL_SERVER_ERROR
 * @property int    $STATUS_NOT_IMPLEMENTED
 * @property int    $STATUS_BAD_GATEWAY
 * @property int    $STATUS_SERVICE_UNAVAILABLE
 * @property int    $STATUS_GATEWAY_TIMEOUT
 * @property int    $STATUS_VERSION_NOT_SUPPORTED
 * @property int    $STATUS_VARIANT_ALSO_NEGOTIATES
 * @property int    $STATUS_INSUFFICIENT_STORAGE
 * @property int    $STATUS_LOOP_DETECTED
 * @property int    $STATUS_NOT_EXTENDED
 * @property int    $STATUS_NETWORK_AUTHENTICATION_REQUIRED
 */
class Http implements RequestMethodInterface, StatusCodeInterface
{
    /**
     * @param string $name
     *
     * @return string|int
     */
    public function __get($name)
    {
        $constant = \get_class($this) . '::' . $name;

        if (false === \defined($constant)) {
            throw new InvalidArgumentException(sprintf('Undefined constant: %s', $constant));
        }

        return \constant($constant);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return \defined(\get_class($this) . '::' . $name);
    }

    /**
     * @param string     $name
     * @param mixed|null $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        throw new LogicException(sprintf('Cannot modify constant: %s::%s', \get_class($this), $name));
    }
}
