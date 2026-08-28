<?php

namespace Ecard\Cms\Exception;

class AppConfigException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_CONFIG_DIR = 'Missing parameter: Config directory';
    const MSG_CANNOT_ACCESS_CONFIG_DIR = 'Config directory is unaccessible';
    const MSG_CANNOT_ACCESS_CONFIG_FILE = 'Config file is unaccessible';
    const MSG_CANNOT_DECODE_CONFIG_FILE = 'Unable to decode config file content';
}
