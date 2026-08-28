<?php

namespace Ecard\Cms\App;

use Ecard\Cms\App\Api\Client;
use Ecard\Cms\Dependencies\Unirest\Request\Body;
use Ecard\Cms\Dependencies\Unirest\Response;
use Ecard\Cms\Exception\ApiException;
use ReflectionClass;
use stdClass;

final class Api
{
    /** @var stdClass */
    private $config;

    /** @var Client */
    private $httpClient;

    /**
     * @param stdClass $config
     * @param Client   $httpClient
     *
     * @return void
     */
    public function __construct(
        $config = null,
        $httpClient = null
    ) {
        if (true === empty($config)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_CONFIG);
        }

        if ('stdClass' !== \get_class($config)) {
            throw new ApiException(ApiException::MSG_INVALID_PARAMETER_CONFIG);
        }

        if (true === empty($httpClient)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_HTTPCLIENT);
        }

        $reflectedHttpClient = new ReflectionClass($httpClient);
        if ('Client' !== $reflectedHttpClient->getShortName()) {
            throw new ApiException(ApiException::MSG_INVALID_PARAMETER_HTTPCLIENT);
        }

        $this->config = $config;

        $this->httpClient = $httpClient;
    }

    /**
     * @param string $mode  'prod'|'staging'
     * @param string $uid   the merchant uid
     * @param int    $price the price in cents
     *
     * @return Response
     */
    public function paymentSchedule(
        $mode,
        $uid,
        $price
    ) {
        if (true === empty($mode)) {
            throw new ApiException(ApiException::MSG_MISSING_PARAMETER_MODE);
        }

        if (true !== \in_array($mode, $this->config->enabled_modes, true)) {
            throw new ApiException(ApiException::MSG_INVALID_PARAMETER_MODE);
        }

        $path = str_replace(
            '<merchant_uid>',
            $uid,
            $this->config->endpoints->payment_schedule
        );

        $url = $this->config->scheme
            . '://'
            . $this->config->host->{$mode}->back
            . $path;

        $params = [
            'created' => date('Y-m-d'),
            'amount_cents' => intval($price),
        ];

        $body = Body::Json($params);
        $ret = $this->httpClient->post($url, $body);

        return $ret;
    }
}
