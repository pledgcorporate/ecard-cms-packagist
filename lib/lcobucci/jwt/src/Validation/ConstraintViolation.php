<?php

namespace Ecard\Cms\Dependencies\Lcobucci\JWT\Validation;

use Ecard\Cms\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;

final class ConstraintViolation extends RuntimeException implements Exception
{
}
