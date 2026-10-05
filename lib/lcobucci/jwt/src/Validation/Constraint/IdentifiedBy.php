<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;

final class IdentifiedBy implements Constraint
{
    /** @var string */
    private $id;

    /** @param string $id */
    public function __construct($id)
    {
        $this->id = $id;
    }

    public function assert(Token $token)
    {
        if (! $token->isIdentifiedBy($this->id)) {
            throw new ConstraintViolation(
                'The token is not identified with the expected ID'
            );
        }
    }
}
