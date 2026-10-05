<?php

namespace Ecard\Cms\App\Exception;

class E164Exception extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_PHONENUMBER = 'Missing parameter: phone number';
    const MSG_INVALID_PARAMETER_PHONENUMBER = 'Invalid parameter: phone number';
    const MSG_INVALID_COUNTRY_CODE = 'Invalid parameter: country code';
}
