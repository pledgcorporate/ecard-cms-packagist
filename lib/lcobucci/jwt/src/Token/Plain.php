<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Token;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Token;
use function class_alias;

class_exists(Plain::class, false) || class_alias(Token::class, Plain::class);
