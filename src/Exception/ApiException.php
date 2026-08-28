<?php

namespace Ecard\Cms\Exception;

class ApiException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_CONFIG = 'Missing parameter: Api config';
    const MSG_MISSING_PARAMETER_MODE = 'Missing parameter: Mode ("prod"|"staging")';
    const MSG_MISSING_PARAMETER_HTTPCLIENT = 'Missing parameter: Http client';
    const MSG_INVALID_PARAMETER_CONFIG = 'Invalid parameter: Api config';
    const MSG_INVALID_PARAMETER_MODE = 'Invalid parameter: Mode ("prod"|"staging")';
    const MSG_INVALID_PARAMETER_HTTPCLIENT = 'Invalid parameter: Http client';
}
