<?php

namespace Ecard\Cms;

use Ecard\Cms\App\Component\Api;
use Ecard\Cms\App\Component\Api\Client;
use Ecard\Cms\App\Component\Helper;
use Ecard\Cms\App\Component\I18n;
use Ecard\Cms\App\Config;
use Ecard\Cms\App\Exception\AppException;
use stdClass;

final class App
{
    /** @var App */
    private static $instance;

    /** @var stdClass */
    public $parameters;

    /** @var Config */
    public $config;

    /** @var Helper */
    public $helper;

    /** @var I18n */
    public $i18n;

    /** @var Api */
    public $api;

    /** @var Client */
    public $http_client;

    /**
     * @return void
     */
    private function __construct()
    {
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
            self::$instance = new self();
        }

        self::$instance->setup($parameters);

        return self::$instance;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return void
     */
    private function setup(
        $parameters = []
    ) {
        // helpers:
        $this->helper = new Helper($this);

        $this->parameters = $this->helper->misc->arrayToStdClass($parameters);

        // config:
        $this->config = new Config();

        // translations:
        $this->i18n = new I18n($this);

        // Http client for api calls:
        $this->http_client = new Client($this);

        // Api endpoints:
        $this->api = new Api($this);
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new AppException(AppException::MSG_UNALLOWED_SINGLETON_UNSERIALIZATION);
    }
}
