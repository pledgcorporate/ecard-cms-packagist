<?php

namespace Ecard\Cms\App\Component\Helper;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Exception\E164Exception;
use Ecard\Cms\Dependencies\libphonenumber\NumberParseException;
use Ecard\Cms\Dependencies\libphonenumber\PhoneNumber;
use Ecard\Cms\Dependencies\libphonenumber\PhoneNumberFormat;
use Ecard\Cms\Dependencies\libphonenumber\PhoneNumberUtil;

class E164 extends Component
{
    /** @var PhoneNumberUtil */
    private $phoneNumberUtil;

    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * @param string $number
     * @param string $region
     *
     * @return string
     */
    public function normalize(
        $number = null,
        $region = null
    ) {
        if (true === empty($number)) {
            throw new E164Exception(E164Exception::MSG_MISSING_PARAMETER_PHONENUMBER);
        }

        $number = strtr(
            $number,
            [
                ' ' => '',
                '-' => '',
                '.' => '',
                '_' => '',
                ',' => '',
                '/' => '',
            ]
        );

        $oPhoneNumber = $this->parse($number, $region);

        if (true === empty($oPhoneNumber)) {
            return $number;
        }

        $result = $this->phoneNumberUtil->format($oPhoneNumber, PhoneNumberFormat::E164);

        return $result;
    }

    /**
     * @param string $number
     * @param string $region
     *
     * @return PhoneNumber|null
     */
    private function parse(
        $number,
        $region = null
    ) {
        if (true === empty($number)) {
            throw new E164Exception(E164Exception::MSG_MISSING_PARAMETER_PHONENUMBER);
        }

        $oPhoneNumber = null;

        try {
            $oPhoneNumber = $this->phoneNumberUtil->parse($number, $region);
        } catch (NumberParseException $e) {
            $errorType = $e->getErrorType();
            switch ($errorType) {
                case NumberParseException::INVALID_COUNTRY_CODE:
                    $msg = E164Exception::MSG_INVALID_COUNTRY_CODE;
                    break;
                case NumberParseException::NOT_A_NUMBER:
                    $msg = E164Exception::MSG_MISSING_PARAMETER_PHONENUMBER;
                    break;
                case NumberParseException::TOO_SHORT_NSN:
                case NumberParseException::TOO_SHORT_AFTER_IDD:
                case NumberParseException::TOO_LONG:
                default:
                    $msg = E164Exception::MSG_INVALID_PARAMETER_PHONENUMBER;
                    break;
            }
            throw new E164Exception($msg);
        }

        $isValidNumber = $this->phoneNumberUtil->isValidNumber($oPhoneNumber);

        if (true !== $isValidNumber) {
            return null;
        }

        return $oPhoneNumber;
    }
}
