<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;

interface Constraint
{
    /** @throws ConstraintViolation */
    public function assert(Token $token);
}
