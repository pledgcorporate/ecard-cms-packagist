<?php

namespace Ecard\Cms\App\Api;

use Ecard\Cms\Dependencies\Unirest\Method;
use Ecard\Cms\Dependencies\Unirest\Request;
use Ecard\Cms\Dependencies\Unirest\Response;
use Ecard\Cms\Exception\ApiClientException;
use stdClass;

class Client
{
    /** @var stdClass */
    private $config;

    /**
     * @param stdClass $config
     *
     * @return void
     */
    public function __construct(
        $config = null
    ) {
        if (true === empty($config)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_CONFIG);
        }

        if ('stdClass' !== \get_class($config)) {
            throw new ApiClientException(ApiClientException::MSG_INVALID_PARAMETER_CONFIG);
        }

        $this->config = $config;
    }

    /**
     * Perform a GET request.
     *
     * @param string                $url
     * @param array<string, mixed>  $queryParams
     * @param array<string, string> $headers
     *
     * @throws ApiClientException
     *
     * @return Response
     */
    public function get(
        $url = '',
        array $queryParams = [],
        array $headers = []
    ) {
        if (true === empty($url)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_ENDPOINT);
        }

        return $this->request(
            Method::GET,
            $url,
            $queryParams,
            $headers
        );
    }

    /**
     * Perform a POST request.
     *
     * @param string                      $url
     * @param string|array<string, mixed> $data
     * @param array<string, string>       $headers
     *
     * @throws ApiClientException
     *
     * @return Response
     */
    public function post(
        $url = '',
        $data = [],
        array $headers = []
    ) {
        if (true === empty($url)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_ENDPOINT);
        }

        return $this->request(
            Method::POST,
            $url,
            $data,
            $headers
        );
    }

    /**
     * Perform a PUT request.
     *
     * @param string                      $url
     * @param string|array<string, mixed> $data
     * @param array<string, string>       $headers
     *
     * @throws ApiClientException
     *
     * @return Response
     */
    public function put(
        $url = '',
        $data = [],
        array $headers = []
    ) {
        if (true === empty($url)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_ENDPOINT);
        }

        return $this->request(
            Method::PUT,
            $url,
            $data,
            $headers
        );
    }

    /**
     * Perform a DELETE request.
     *
     * @param string                $url
     * @param array<string, mixed>  $queryParams
     * @param array<string, string> $headers
     *
     * @throws ApiClientException
     *
     * @return Response
     */
    public function delete(
        $url = '',
        array $queryParams = [],
        array $headers = []
    ) {
        if (true === empty($url)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_ENDPOINT);
        }

        return $this->request(
            Method::DELETE,
            $url,
            $queryParams,
            $headers
        );
    }

    /**
     * Core request executor using cURL.
     *
     * @param string                      $method
     * @param string                      $url
     * @param string|array<string, mixed> $data
     * @param array<string, string>       $headers
     *
     * @throws ApiClientException
     *
     * @return Response
     */
    private function request(
        $method,
        $url,
        $data = null,
        array $headers = []
    ) {
        if (true === empty($method)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_METHOD);
        }

        if (true === empty($url)) {
            throw new ApiClientException(ApiClientException::MSG_MISSING_PARAMETER_ENDPOINT);
        }

        $mergedHeaders = array_merge(
            $this->getDefaultHeaders(),
            $headers
        );

        $request = new Request();

        // Set JSON decode mode so we get response body as associative array
        $request->jsonOpts($this->config->return_as_associative_array);

        $response = $request->send($method, $url, $data, $mergedHeaders);

        return $response;
    }

    /**
     * retrieves the default headers always used by http client.
     *
     * @return array<string, string>
     */
    private function getDefaultHeaders()
    {
        $ret = [];

        if (true === property_exists($this->config, 'default_headers')) {
            $ret = get_object_vars($this->config->default_headers);
        }

        return $ret;
    }
}
