<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\NoConstraintsGiven;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\RequiredConstraintsViolated;

interface Validator
{
    /**
     * @throws RequiredConstraintsViolated
     * @throws NoConstraintsGiven
     */
    public function assert(Token $token, Constraint ...$constraints);

    /**
     * @return bool
     *
     * @throws NoConstraintsGiven
     */
    public function validate(Token $token, Constraint ...$constraints);
}
