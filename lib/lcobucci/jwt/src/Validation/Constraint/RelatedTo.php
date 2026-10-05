<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;

final class RelatedTo implements Constraint
{
    /** @var string */
    private $subject;

    public function __construct($subject)
    {
        $this->subject = $subject;
    }

    public function assert(Token $token)
    {
        if (! $token->isRelatedTo($this->subject)) {
            throw new ConstraintViolation(
                'The token is not related to the expected subject'
            );
        }
    }
}
