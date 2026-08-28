<?php

namespace Ecard\Cms;

use Ecard\Cms\App\Api;
use Ecard\Cms\App\Api\Client;
use Ecard\Cms\App\Config;
use Ecard\Cms\App\I18n;
use Ecard\Cms\App\JWTSignature;
use Ecard\Cms\Exception\AppException;

final class App
{
    /** @var App */
    private static $instance;

    /** @var array<string, mixed> */
    public $parameters = [];

    /** @var Config */
    public $config;

    /** @var I18n */
    public $i18n;

    /** @var Api */
    public $api;

    /** @var JWTSignature */
    public $jwt;

    /** @var Client */
    public $http_client;

    /**
     * @param array<string, mixed> $parameters
     *
     * @return void
     */
    private function __construct(
        $parameters = []
    ) {
        if (
            true === empty($parameters)
            || true !== \is_array($parameters)
        ) {
            $parameters = [];
        }

        $this->parameters = $parameters;
        $this->config = new Config();
        $this->setup();
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return App
     */
    public static function get(
        $parameters = []
    ) {
        if (null === self::$instance) {
            self::$instance = new self($parameters);
        }

        return self::$instance;
    }

    /**
     * @return void
     */
    private function setup()
    {
        $locale = null;
        if (true === \array_key_exists('locale', $this->parameters)) {
            $locale = trim($this->parameters['locale']);
        }

        // translations:
        $this->i18n = new I18n($this->config->i18n, $locale);

        // Http client for api calls:
        $this->http_client = new Client($this->config->http_client);

        // Api endpoints:
        $this->api = new Api(
            $this->config->api,
            $this->http_client
        );

        // JWT signatures:
        $this->jwt = new JWTSignature();
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new AppException(AppException::MSG_UNALLOWED_SINGLETON_UNSERIALIZATION);
    }
}
