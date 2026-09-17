<?php

namespace Ecard\Cms\App;

use Ecard\Cms\App;
use Ecard\Cms\App\Exception\ComponentException;

abstract class Component
{
    /** @var App */
    protected $app;

    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        if (true === empty($app)) {
            throw new ComponentException(ComponentException::MSG_MISSING_PARAMETER_APP);
        }

        if ('Ecard\Cms\App' !== \get_class($app)) {
            throw new ComponentException(ComponentException::MSG_INVALID_PARAMETER_APP);
        }

        $this->app = $app;
    }
}
