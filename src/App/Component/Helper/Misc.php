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
     * - used by api calls when returning result.
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
            && (
                true === is_array($data)
                || true === is_object($data)
            )
        ) {
            if (true === is_object($data)) {
                $data = get_object_vars($data);
            }

            foreach ($data as $key => $value) {
                if (
                    true === is_array($value)
                    || true === is_object($value)
                ) {
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

        $priceInCents = $priceInUnits * 100;
        $priceInCents = round($priceInCents);
        $priceInCents = intval($priceInCents);

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

        $priceInUnits = $priceInCents / 100;
        $priceInUnits = number_format($priceInUnits, 2, '.', '');
        $priceInUnits = floatval($priceInUnits);

        return $priceInUnits;
    }

    /**
     * checks if $uid is a valid pledg merchant uid (e.g. 'mer_<uuid>').
     *
     * @param string|null $uid
     *
     * @return bool
     */
    public function isValidMerchantUid(
        $uid
    ) {
        return $this->isMatchingRegex(
            $this->app->config->misc->regex->pledg_merchant_uid,
            $uid
        );
    }

    /**
     * checks if $uid is a valid pledg company uid (e.g. 'cmp_<uuid>').
     *
     * @param string|null $uid
     *
     * @return bool
     */
    public function isValidCompanyUid(
        $uid
    ) {
        return $this->isMatchingRegex(
            $this->app->config->misc->regex->pledg_company_uid,
            $uid
        );
    }

    /**
     * checks if $uid is a valid pledg merchant group uid (e.g. 'mgrp_<uuid>').
     *
     * @param string|null $uid
     *
     * @return bool
     */
    public function isValidMerchantGroupUid(
        $uid
    ) {
        return $this->isMatchingRegex(
            $this->app->config->misc->regex->pledg_merchant_group_uid,
            $uid
        );
    }

    /**
     * checks if $uid is not empty and matches $regex.
     *
     * @param string      $regex
     * @param string|null $uid
     *
     * @return bool
     */
    private function isMatchingRegex(
        $regex,
        $uid
    ) {
        if (true === empty($uid)) {
            return false;
        }

        $match = preg_match($regex, $uid);
        if (1 === $match) {
            return true;
        }

        return false;
    }
}
