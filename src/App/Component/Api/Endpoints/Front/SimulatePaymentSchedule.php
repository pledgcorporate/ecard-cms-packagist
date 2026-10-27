<?php

namespace Ecard\Cms\App\Component\Api\Endpoints\Front;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\Dependencies\Unirest\Request\Body;
use Ecard\Cms\Dependencies\Unirest\Response;

final class SimulatePaymentSchedule extends Component
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
     * @param string $mode  'prod'|'staging'
     * @param string $uid   the merchant uid
     * @param int    $price the price in cents
     *
     * @return Response
     */
    public function call(
        $mode,
        $uid,
        $price
    ) {
        $path = str_replace(
            '<merchant_uid>',
            $uid,
            $this->app->config->api->endpoints->front->payment_schedule
        );

        $url = $this->app->config->api->scheme
            . '://'
            . $this->app->config->api->host->{$mode}->back
            . $path;

        $params = [
            'created' => date('Y-m-d'),
            'amount_cents' => \intval($price),
        ];

        $body = Body::Json($params);
        $ret = $this->app->http_client->post($url, $body);

        return $ret;
    }
}
