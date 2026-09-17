<?php

namespace Ecard\Cms\App\Exception;

class JWTSignatureException extends EcardCmsException
{
    const MSG_MISSING_PARAMETER_PAYLOAD = 'Missing parameter: Payload';
    const MSG_MISSING_PARAMETER_SIGNINGKEY = 'Missing parameter: Signing Key';
    const MSG_MISSING_PARAMETER_TOKEN = 'Missing parameter: Token';
    const MSG_DECODING_TOKEN_IMPOSSIBLE = 'JWT token is impossible to decode';
    const MSG_DECODING_TOKEN_INVALID_STRUCTURE = 'JWT token has invalid structure';
    const MSG_DECODING_TOKEN_UNSUPPORTED_HEADER = 'JWT token has an unsupported header';
    const MSG_MISSING_PARAMETER_APP = 'Missing parameter: app';
    const MSG_INVALID_PARAMETER_APP = 'Invalid parameter: app';
}
