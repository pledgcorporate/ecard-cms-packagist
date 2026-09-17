<?php

namespace Ecard\Cms\App\Component\Helper;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use stdClass;

class Misc extends Component
{
    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);
    }

    /**
     * Recursively casts an array to a stdClass object
     * - used by JWTSignature when decoding a jwt message.
     *
     * @param mixed|array<string, mixed> $data
     *
     * @return stdClass
     */
    public function arrayToStdClass(
        $data
    ) {
        $object = new stdClass();

        if (
            true !== empty($data)
            && true === is_array($data)
        ) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $object->{$key} = $this->arrayToStdClass($value);
                } else {
                    $object->{$key} = $value;
                }
            }
        }

        return $object;
    }

    /**
     * We check if $price is between $min and $max amounts.
     * $min, $max and $price are amounts in cents.
     *
     * @param int|null $min
     * @param int|null $max
     * @param int|null $price
     *
     * @return bool
     */
    public function amountIsInPriceRange(
        $min = 0,
        $max = 0,
        $price = 0
    ) {
        $ret = false;

        if (true === empty($min)) {
            $min = 0;
        }

        if (true === empty($max)) {
            $max = 0;
        }

        if (true === empty($price)) {
            $price = 0;
        }

        if (
            (0 === $max || $price <= $max)
            && (0 === $min || $price >= $min)
        ) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * converts units (e.g. euros) to cents (e.g. eurocents).
     *
     * @param float|null $priceInUnits
     *
     * @return int
     */
    public function convertUnitsToCents(
        $priceInUnits = 0.0
    ) {
        if (true === empty($priceInUnits)) {
            $priceInUnits = 0.0;
        }

        $priceInUnits = round($priceInUnits, 2);
        $priceInCents = intval($priceInUnits * 100);

        return $priceInCents;
    }

    /**
     * converts cents (e.g. eurocents) to units (e.g. euros).
     *
     * @param int|null $priceInCents
     *
     * @return float
     */
    public function convertCentsToUnits(
        $priceInCents = 0
    ) {
        if (true === empty($priceInCents)) {
            $priceInCents = 0;
        }

        return floatval($priceInCents / 100);
    }
}
