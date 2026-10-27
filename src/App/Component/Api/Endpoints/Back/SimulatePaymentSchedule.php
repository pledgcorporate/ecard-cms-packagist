<?php

namespace Ecard\Cms\App\Component\Api\Endpoints\Back;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\Dependencies\Unirest\Request\Body;
use Ecard\Cms\Dependencies\Unirest\Response;

/**
 * Simulates a payment schedule through the pledg back api (requires an access token).
 *
 * @see https://docs.pledg.co/integration/api/payment_schedule_simulation.html
 */
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
     * @param string $mode        'prod'|'staging'
     * @param string $uid         the merchant uid
     * @param int    $price       the price in cents
     * @param string $accessToken the access token (see Token endpoint)
     *
     * @return Response
     */
    public function call(
        $mode,
        $uid,
        $price,
        $accessToken
    ) {
        $path = str_replace(
            '<merchant_uid>',
            $uid,
            $this->app->config->api->endpoints->back->payment_schedule
        );

        $url = $this->app->config->api->scheme
            . '://'
            . $this->app->config->api->host->{$mode}->back
            . $path;

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        $params = [
            'created' => date('Y-m-d'),
            'amount_cents' => \intval($price),
        ];

        $body = Body::Json($params);
        $ret = $this->app->http_client->post($url, $body, $headers);

        return $ret;
    }
}
