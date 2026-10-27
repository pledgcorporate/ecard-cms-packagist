<?php

namespace Ecard\Cms\App\Exception;

class ApiException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_APP = 'Missing parameter: app';
    const MSG_INVALID_PARAMETER_APP = 'Invalid parameter: app';
    const MSG_MISSING_PARAMETER_MODE = 'Missing parameter: Mode ("prod"|"staging")';
    const MSG_INVALID_PARAMETER_MODE = 'Invalid parameter: Mode ("prod"|"staging")';
    const MSG_MISSING_PARAMETER_UID = 'Missing parameter: uid';
    const MSG_INVALID_PARAMETER_UID = 'Invalid parameter: uid';
    const MSG_MISSING_PARAMETER_PRICE = 'Missing parameter: price';
    const MSG_INVALID_PARAMETER_PRICE = 'Invalid parameter: price';
    const MSG_INVALID_PARAMETER_MERCHANT_UID = 'Invalid parameter: merchant uid';
    const MSG_INVALID_PARAMETER_COMPANY_UID = 'Invalid parameter: company uid';
    const MSG_INVALID_PARAMETER_MERCHANT_GROUP_UID = 'Invalid parameter: merchant group uid';
    const MSG_MISSING_PARAMETER_API_KEY = 'Missing parameter: api key';
    const MSG_MISSING_PARAMETER_API_SECRET = 'Missing parameter: api secret';
    const MSG_MISSING_PARAMETER_ACCESS_TOKEN = 'Missing parameter: access token';
}
