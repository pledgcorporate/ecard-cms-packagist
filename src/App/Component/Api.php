<?php

namespace Ecard\Cms\App\Component;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Component\Api\Endpoints\Back\SimulatePaymentSchedule as BackSimulatePaymentSchedule;
use Ecard\Cms\App\Component\Api\Endpoints\Back\Token as BackToken;
use Ecard\Cms\App\Component\Api\Endpoints\Front\SimulatePaymentSchedule as FrontSimulatePaymentSchedule;
use Ecard\Cms\App\Exception\ApiException;
use stdClass;

final class Api extends Component
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
     * Retrieves an access token (valid for 'expires_in' seconds) to be used on secured back api endpoints.
     * On success, the returned body holds 'access_token', 'token_type' ('Bearer') and 'expires_in'.
     * Never log the credentials nor the returned access token.
     *
     * @param string|null $mode      'prod'|'staging'
     * @param string|null $apiKey    the api key
     * @param string|null $apiSecret the api secret
     *
     * @return stdClass
     */
    public function token(
        $mode,
        $apiKey,
        $apiSecret
    ) {
        $this->validateMode($mode);

        if (true === empty($apiKey)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_API_KEY);
        }

        if (true === empty($apiSecret)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_API_SECRET);
        }

        $apiToken = new BackToken($this->app);

        // parameters have been validated above
        $response = $apiToken->call(
            (string)$mode,
            $apiKey,
            $apiSecret
        );

        $ret = $this->app->helper->misc->arrayToStdClass($response);

        return $ret;
    }

    /**
     * @param string|null $mode  'prod'|'staging'
     * @param string|null $uid   the merchant uid
     * @param int|null    $price the price in cents
     *
     * @return stdClass
     */
    public function frontPaymentSchedule(
        $mode,
        $uid,
        $price
    ) {
        $this->validatePaymentScheduleParameters(
            $mode,
            $uid,
            $price
        );

        $apiSimulatePaymentSchedule = new FrontSimulatePaymentSchedule($this->app);

        // parameters have been validated above
        $response = $apiSimulatePaymentSchedule->call(
            (string)$mode,
            (string)$uid,
            (int)$price
        );

        $ret = $this->app->helper->misc->arrayToStdClass($response);

        return $ret;
    }

    /**
     * @param string|null $mode        'prod'|'staging'
     * @param string|null $uid         the merchant uid
     * @param int|null    $price       the price in cents
     * @param string|null $accessToken the access token (see token() method)
     *
     * @return stdClass
     */
    public function backPaymentSchedule(
        $mode,
        $uid,
        $price,
        $accessToken
    ) {
        $this->validatePaymentScheduleParameters(
            $mode,
            $uid,
            $price
        );

        if (true === empty($accessToken)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_ACCESS_TOKEN);
        }

        $apiSimulatePaymentSchedule = new BackSimulatePaymentSchedule($this->app);

        // parameters have been validated above
        $response = $apiSimulatePaymentSchedule->call(
            (string)$mode,
            (string)$uid,
            (int)$price,
            $accessToken
        );

        $ret = $this->app->helper->misc->arrayToStdClass($response);

        return $ret;
    }

    /**
     * @param string|null $mode 'prod'|'staging'
     *
     * @throws ApiException
     *
     * @return void
     */
    private function validateMode(
        $mode
    ) {
        if (true === empty($mode)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_MODE);
        }

        if (true !== \in_array($mode, $this->app->config->api->enabled_modes, true)) {
            throw new ApiException(ApiException::MSG_INVALID_PARAMETER_MODE);
        }
    }

    /**
     * @param string|null $mode  'prod'|'staging'
     * @param string|null $uid   the merchant uid
     * @param int|null    $price the price in cents
     *
     * @throws ApiException
     *
     * @return void
     */
    private function validatePaymentScheduleParameters(
        $mode,
        $uid,
        $price
    ) {
        $this->validateMode($mode);

        if (true === empty($price)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_PRICE);
        }

        if (true === empty($uid)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_UID);
        }

        if (true !== $this->app->helper->misc->isValidMerchantUid($uid)) {
            throw new ApiException(ApiException::MSG_INVALID_PARAMETER_MERCHANT_UID);
        }
    }
}
