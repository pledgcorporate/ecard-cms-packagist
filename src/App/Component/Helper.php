<?php

namespace Ecard\Cms\App\Component;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Component\Helper\E164;
use Ecard\Cms\App\Component\Helper\JWTSignature;
use Ecard\Cms\App\Component\Helper\Misc;

final class Helper extends Component
{
    /** @var Misc */
    public $misc;

    /** @var JWTSignature */
    public $jwt;

    /** @var E164 */
    public $e164;

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

        // e164 helper:
        $this->e164 = new E164($app);
    }
}
