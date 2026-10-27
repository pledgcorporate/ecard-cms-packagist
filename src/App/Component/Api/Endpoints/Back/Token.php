<?php

namespace Ecard\Cms\App\Component\Api\Endpoints\Back;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\Dependencies\Unirest\Request\Body;
use Ecard\Cms\Dependencies\Unirest\Response;

/**
 * Retrieves an access token from the pledg back api (client credentials grant).
 *
 * @see https://docs.pledg.co/integration/api/authentication.html#access-token
 */
final class Token extends Component
{
    const GRANT_TYPE = 'client_credentials';

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
     * Never log the credentials nor the returned access token.
     *
     * @param string $mode      'prod'|'staging'
     * @param string $apiKey    the api key (used as basic auth username)
     * @param string $apiSecret the api secret (used as basic auth password)
     *
     * @return Response
     */
    public function call(
        $mode,
        $apiKey,
        $apiSecret
    ) {
        $url = $this->app->config->api->scheme
            . '://'
            . $this->app->config->api->host->{$mode}->back
            . $this->app->config->api->endpoints->back->token;

        $headers = [
            // overrides the default 'application/json' content type of the http client
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
        ];

        $body = Body::Form([
            'grant_type' => self::GRANT_TYPE,
        ]);

        $ret = $this->app->http_client->post($url, $body, $headers);

        return $ret;
    }
}
