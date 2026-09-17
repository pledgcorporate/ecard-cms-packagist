<?php

namespace Ecard\Cms\App\Exception;

class I18nException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_APP = 'Missing parameter: app';
    const MSG_INVALID_PARAMETER_APP = 'Invalid parameter: app';
    const MSG_MISSING_PARAMETER_TRANSLATIONKEY = 'Missing parameter: Translation key';
}
