<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;

final class PermittedFor implements Constraint
{
    /** @var string  */
    private $audience;

    public function __construct($audience)
    {
        $this->audience = $audience;
    }

    public function assert(Token $token)
    {
        if (! $token->isPermittedFor($this->audience)) {
            throw new ConstraintViolation(
                'The token is not allowed to be used by this audience'
            );
        }
    }
}
