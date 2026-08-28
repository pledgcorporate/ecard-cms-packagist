<?php

namespace Ecard\Cms\Exception;

class I18nException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_CONFIG = 'Missing parameter: config';
    const MSG_INVALID_PARAMETER_CONFIG = 'Invalid parameter: config';
    const MSG_MISSING_PARAMETER_TRANSLATIONKEY = 'Missing parameter: Translation key';
}
