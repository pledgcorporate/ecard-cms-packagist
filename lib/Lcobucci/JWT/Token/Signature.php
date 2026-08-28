<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Token;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Signature as SignatureImpl;
use function class_alias;

class_exists(Signature::class, false) || class_alias(SignatureImpl::class, Signature::class);
