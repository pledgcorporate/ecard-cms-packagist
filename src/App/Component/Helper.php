<?php

namespace Ecard\Cms\App\Component;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Component\Helper\JWTSignature;
use Ecard\Cms\App\Component\Helper\Misc;

final class Helper extends Component
{
    /** @var Misc */
    public $misc;

    /** @var JWTSignature */
    public $jwt;

    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);

        // misc helper:
        $this->misc = new Misc($app);

        // jwt helper:
        $this->jwt = new JWTSignature($app);
    }
}
