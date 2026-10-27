<?php

namespace Ecard\Cms;

use Ecard\Cms\App\Component\Api;
use Ecard\Cms\App\Component\Api\Client;
use Ecard\Cms\App\Component\Helper;
use Ecard\Cms\App\Component\Http\Request;
use Ecard\Cms\App\Component\Http\RequestFactory;
use Ecard\Cms\App\Component\Http\Response;
use Ecard\Cms\App\Component\I18n;
use Ecard\Cms\App\Config;
use Ecard\Cms\App\Exception\AppException;
use stdClass;

require_once __DIR__ . '/../lib/autoload.php';

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

    /** @var RequestFactory */
    public $request_factory;

    /**
     * the incoming http request (built by the first App::get() call, then kept across App::get() calls).
     *
     * @var Request
     */
    public $request;

    /**
     * the outgoing http response (built by the first App::get() call, then kept across App::get() calls).
     *
     * @var Response
     */
    public $response;

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

        // factory of objects representing the incoming HTTP request
        // (immutable: configure it with its with*() methods, which return a copy):
        $this->request_factory = new RequestFactory($this);

        // incoming http request (built once, unlike the components):
        if (null === $this->request) {
            $this->request = $this->request_factory->fromGlobals();
        }

        // outgoing http response (built once: the status code and headers are global to the php process):
        if (null === $this->response) {
            $this->response = new Response($this->request);
        }
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new AppException(AppException::MSG_UNALLOWED_SINGLETON_UNSERIALIZATION);
    }
}
