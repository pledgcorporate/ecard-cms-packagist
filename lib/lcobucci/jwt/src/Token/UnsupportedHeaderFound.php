<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Token;

use InvalidArgumentException;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Exception;

final class UnsupportedHeaderFound extends InvalidArgumentException implements Exception
{
    /** @return self */
    public static function encryption()
    {
        return new self('Encryption is not supported yet');
    }
}
