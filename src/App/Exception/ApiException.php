<?php

namespace Ecard\Cms\App\Exception;

class ApiException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_APP = 'Missing parameter: app';
    const MSG_INVALID_PARAMETER_APP = 'Invalid parameter: app';
    const MSG_MISSING_PARAMETER_MODE = 'Missing parameter: Mode ("prod"|"staging")';
    const MSG_INVALID_PARAMETER_MODE = 'Invalid parameter: Mode ("prod"|"staging")';
}
