<?php

class_exists(\Ecard\Cms\Dependencies\Lcobucci\JWT\Token\Plain::class, false) || class_alias(\Ecard\Cms\Dependencies\Lcobucci\JWT\Token::class, \Ecard\Cms\Dependencies\Lcobucci\JWT\Token\Plain::class);
class_exists(\Ecard\Cms\Dependencies\Lcobucci\JWT\Token\Signature::class, false) || class_alias(\Ecard\Cms\Dependencies\Lcobucci\JWT\Signature::class, \Ecard\Cms\Dependencies\Lcobucci\JWT\Token\Signature::class);
