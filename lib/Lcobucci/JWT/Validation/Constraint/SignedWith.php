<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Signer;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Ecard\Cms\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;

final class SignedWith implements Constraint
{
    /** @var Signer */
    private $signer;

    /** @var Signer\Key */
    private $key;

    public function __construct(Signer $signer, Signer\Key $key)
    {
        $this->signer = $signer;
        $this->key    = $key;
    }

    public function assert(Token $token)
    {
        if ($token->headers()->get('alg') !== $this->signer->getAlgorithmId()) {
            throw new ConstraintViolation('Token signer mismatch');
        }

        if (! $this->signer->verify((string) $token->signature(), $token->getPayload(), $this->key)) {
            throw new ConstraintViolation('Token signature mismatch');
        }
    }
}
