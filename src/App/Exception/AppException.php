<?php

namespace Ecard\Cms\App\Exception;

class AppException extends EcardCmsException
{
    const MSG_UNALLOWED_SINGLETON_UNSERIALIZATION = 'Unallowed serialization of TranslationHelper singleton';
}
