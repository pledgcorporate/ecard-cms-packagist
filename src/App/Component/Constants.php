<?php

namespace Ecard\Cms\App\Component;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Component\Constants\Http;

final class Constants extends Component
{
    /** @var Http */
    public $http;

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
        $this->http = new Http();
    }
}
