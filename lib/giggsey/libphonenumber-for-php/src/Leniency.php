<?php

namespace Ecard\Cms\Dependencies\libphonenumber;

use Ecard\Cms\Dependencies\libphonenumber\Leniency\Possible;
use Ecard\Cms\Dependencies\libphonenumber\Leniency\StrictGrouping;
use Ecard\Cms\Dependencies\libphonenumber\Leniency\Valid;
use Ecard\Cms\Dependencies\libphonenumber\Leniency\ExactGrouping;

class Leniency
{
    public static function POSSIBLE()
    {
        return new Possible;
    }

    public static function VALID()
    {
        return new Valid;
    }

    public static function STRICT_GROUPING()
    {
        return new StrictGrouping;
    }

    public static function EXACT_GROUPING()
    {
        return new ExactGrouping;
    }
}
