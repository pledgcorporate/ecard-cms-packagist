<?php

namespace Ecard\Cms\App\Component\Http;

use Closure;
use Ecard\Cms\App\Exception\HttpException;

/**
 * Immutable representation of the incoming http request, inspired by Nette\Http\Request.
 * - the incoming request: $request = $app->request;
 *   (other requests can be built by RequestFactory: $app->request_factory->withForceHttps()->fromGlobals()).
 * - header names are case insensitive.
 * - the url getters (getScheme(), getHost(), getBasePath()...) are those of Nette\Http\UrlScript.
 * - the outgoing response (status code, headers, cookies, redirects, json, files) is $app->response (see Response).
 */
final class Request
{
    /** @var array<string, int> */
    private static $defaultPorts = [
        'http' => 80,
        'https' => 443,
    ];

    /** @var string */
    private $method;

    /** @var string */
    private $scheme;

    /** @var string */
    private $host;

    /** @var int|null */
    private $port;

    /** @var string */
    private $path;

    /** @var string */
    private $queryString;

    /** @var string */
    private $scriptPath;

    /** @var array<int|string, mixed> */
    private $query;

    /** @var array<int|string, mixed> */
    private $post;

    /** @var array<int|string, mixed> FileUpload objects (possibly nested in arrays) */
    private $files;

    /** @var array<int|string, mixed> */
    private $cookies;

    /** @var array<string, string> lowercase header name => value */
    private $headers;

    /** @var string|null */
    private $remoteAddress;

    /** @var Closure|null */
    private $rawBodyCallback;

    /** @var string|null */
    private $rawBody;

    /** @var bool */
    private $rawBodyLoaded = false;

    /** @var bool */
    private $postTooLarge;

    /** @var bool */
    private $hostTrusted;

    /**
     * @param array<string, mixed>     $url             'scheme', 'host', 'port', 'path', 'query' (raw query string), 'script_path'
     * @param array<int|string, mixed> $query           $_GET parameters
     * @param array<int|string, mixed> $post            $_POST parameters
     * @param array<int|string, mixed> $files           FileUpload objects (possibly nested in arrays)
     * @param array<int|string, mixed> $cookies         $_COOKIE values
     * @param array<string, string>    $headers         header name => value
     * @param string                   $method          the http method
     * @param string|null              $remoteAddress   the client ip address
     * @param Closure|null             $rawBodyCallback returns the raw body (called once, on the first getRawBody() call)
     * @param bool                     $postTooLarge    whether the body was dropped by php (see isPostTooLarge())
     * @param bool                     $hostTrusted     whether the host requested by the client is trusted (see isHostTrusted())
     *
     * @return void
     */
    public function __construct(
        array $url = [],
        array $query = [],
        array $post = [],
        array $files = [],
        array $cookies = [],
        array $headers = [],
        $method = 'GET',
        $remoteAddress = null,
        $rawBodyCallback = null,
        $postTooLarge = false,
        $hostTrusted = true
    ) {
        if (
            null !== $rawBodyCallback
            && true !== ($rawBodyCallback instanceof Closure)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_RAW_BODY_CALLBACK);
        }

        $this->scheme = (true === isset($url['scheme'])) ? strtolower((string)$url['scheme']) : 'http';
        $this->host = (true === isset($url['host'])) ? strtolower((string)$url['host']) : 'localhost';
        $this->port = (true === isset($url['port'])) ? (int)$url['port'] : null;
        $this->path = (true === isset($url['path']) && '' !== (string)$url['path']) ? (string)$url['path'] : '/';
        $this->queryString = (true === isset($url['query'])) ? ltrim((string)$url['query'], '?') : '';
        $this->scriptPath = (true === isset($url['script_path']) && '' !== (string)$url['script_path']) ? (string)$url['script_path'] : $this->path;

        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->headers = array_change_key_case($headers, \CASE_LOWER);
        $this->method = strtoupper($method);
        $this->remoteAddress = $remoteAddress;
        $this->rawBodyCallback = $rawBodyCallback;
        $this->postTooLarge = (true === $postTooLarge);
        $this->hostTrusted = (true === $hostTrusted);
    }

    /*
     * request data
     */

    /**
     * Returns a GET parameter (or all of them when $key is null).
     *
     * @param int|string|null $key
     * @param mixed|null      $default
     *
     * @return mixed|null
     */
    public function getQuery(
        $key = null,
        $default = null
    ) {
        return $this->getValue($this->query, $key, $default);
    }

    /**
     * Returns a POST parameter (or all of them when $key is null).
     *
     * @param int|string|null $key
     * @param mixed|null      $default
     *
     * @return mixed|null
     */
    public function getPost(
        $key = null,
        $default = null
    ) {
        return $this->getValue($this->post, $key, $default);
    }

    /**
     * Returns an uploaded file.
     * - nested fields (e.g. <input type="file" name="documents[identity]">) are reached with
     *   an array of keys: getFile(['documents', 'identity']).
     *
     * @param int|string|array<int, int|string> $key
     *
     * @return FileUpload|null
     */
    public function getFile(
        $key
    ) {
        $value = $this->files;

        foreach ((array)$key as $subKey) {
            if (
                true !== is_array($value)
                || true !== array_key_exists($subKey, $value)
            ) {
                return null;
            }
            $value = $value[$subKey];
        }

        return ($value instanceof FileUpload) ? $value : null;
    }

    /**
     * Returns all the uploaded files (FileUpload objects, possibly nested in arrays).
     *
     * @return array<int|string, mixed>
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param int|string $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function getCookie(
        $key,
        $default = null
    ) {
        return $this->getValue($this->cookies, $key, $default);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * The uppercase http method (e.g. 'POST').
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method case insensitive
     *
     * @return bool
     */
    public function isMethod(
        $method
    ) {
        return 0 === strcasecmp($this->method, $method);
    }

    /**
     * @param string      $header  case insensitive
     * @param string|null $default
     *
     * @return string|null
     */
    public function getHeader(
        $header,
        $default = null
    ) {
        $header = strtolower($header);

        if (true === isset($this->headers[$header])) {
            return $this->headers[$header];
        }

        return $default;
    }

    /**
     * @return array<string, string> lowercase header name => value
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Whether the request was made over https.
     *
     * @return bool
     */
    public function isSecured()
    {
        return 'https' === $this->scheme;
    }

    /**
     * Whether the request comes from the same site (protection against csrf):
     * - uses the Sec-Fetch-Site header sent by modern browsers,
     * - or, if missing, compares the Origin header with the url of the request.
     * (requests without any of these headers are considered as cross-site).
     *
     * @return bool
     */
    public function isSameSite()
    {
        $fetchSite = $this->getHeader('sec-fetch-site');

        if (null !== $fetchSite) {
            return true === \in_array(strtolower(trim($fetchSite)), ['same-origin', 'same-site', 'none'], true);
        }

        $origin = $this->getHeader('origin');

        if (null !== $origin) {
            return strtolower(rtrim(trim($origin), '/')) === $this->getHostUrl();
        }

        return false;
    }

    /**
     * Whether php has dropped the body of this POST request because it is larger than the 'post_max_size'
     * php.ini setting: in this case, getPost() and getFiles() are empty and php gives no error code,
     * so a form would otherwise look as if it had been sent empty.
     * - detected from the Content-Length header (see FileUpload::getMaxUploadSize() to display the limit).
     *
     * @return bool
     */
    public function isPostTooLarge()
    {
        return $this->postTooLarge;
    }

    /**
     * Whether the host requested by the client (the Host header, or X-Forwarded-Host from a trusted proxy)
     * has been accepted.
     * - false when it was invalid, or not allowed by the trusted hosts (see RequestFactory): getHost() then
     *   returns another host (the first exact trusted host, the server name...), so the urls of this request
     *   are safe, but the client asked for another domain (or port): a host header poisoning attempt,
     *   or a misconfiguration of the trusted hosts. The application can answer with a 400 Bad Request.
     * - true when the client did not send any host (e.g. an http/1.0 request).
     *
     * @return bool
     */
    public function isHostTrusted()
    {
        return $this->hostTrusted;
    }

    /**
     * Whether the request was made by javascript (X-Requested-With: XMLHttpRequest).
     *
     * @return bool
     */
    public function isAjax()
    {
        return 'xmlhttprequest' === strtolower((string)$this->getHeader('x-requested-with'));
    }

    /**
     * The client ip address (resolved through X-Forwarded-For when the request comes from a trusted proxy).
     *
     * @return string|null
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /**
     * The raw body of the request (read only once).
     * - php://input is empty for multipart/form-data requests: use getPost() and getFile() instead.
     *
     * @return string|null
     */
    public function getRawBody()
    {
        if (true !== $this->rawBodyLoaded) {
            $this->rawBodyLoaded = true;

            if (null !== $this->rawBodyCallback) {
                $rawBody = \call_user_func($this->rawBodyCallback);

                if (
                    null !== $rawBody
                    && true !== is_string($rawBody)
                ) {
                    throw new HttpException(HttpException::MSG_CANNOT_READ_RAW_BODY);
                }

                $this->rawBody = $rawBody;
            }
        }

        return $this->rawBody;
    }

    /**
     * Returns the preferred language among the supported ones, according to the Accept-Language header.
     *
     * @param array<int, string> $langs supported languages (e.g. ['en', 'fr', 'it'])
     *
     * @return string|null one of $langs (null if none matches)
     */
    public function detectLanguage(
        array $langs
    ) {
        $header = $this->getHeader('accept-language');

        if (
            null === $header
            || true === empty($langs)
        ) {
            return null;
        }

        // lowercase => original value (longest first: 'fr-ca' must be tried before 'fr')
        $supported = [];
        foreach ($langs as $lang) {
            $supported[strtolower(strtr($lang, '_', '-'))] = $lang;
        }
        krsort($supported);

        $patterns = [];
        foreach (array_keys($supported) as $lang) {
            $patterns[] = preg_quote($lang, '#');
        }

        $header = strtolower(strtr($header, '_', '-'));
        $regex = '#(?:^|,)\s*(' . implode('|', $patterns) . ')(?:-[^\s,;=]+)?\s*(?:;\s*q\s*=\s*([0-9.]+))?\s*(?=,|$)#';

        if (true !== (bool)preg_match_all($regex, $header, $matches)) {
            return null;
        }

        $max = 0.0;
        $result = null;

        foreach ($matches[1] as $key => $lang) {
            $q = ('' === $matches[2][$key]) ? 1.0 : (float)$matches[2][$key];

            if ($q > $max) {
                $max = $q;
                $result = $supported[$lang];
            }
        }

        return $result;
    }

    /**
     * [user, password] of an 'Authorization: Basic ...' header.
     *
     * @return array<int, string>|null
     */
    public function getBasicCredentials()
    {
        $authorization = $this->getHeader('authorization');

        if (
            null === $authorization
            || 1 !== preg_match('/^Basic\s+(\S+)$/i', trim($authorization), $matches)
        ) {
            return null;
        }

        $decoded = base64_decode($matches[1], true);

        if (
            false === $decoded
            || false === strpos($decoded, ':')
        ) {
            return null;
        }

        $credentials = explode(':', $decoded, 2);

        return [$credentials[0], $credentials[1]];
    }

    /*
     * url (getters of Nette\Http\UrlScript)
     */

    /**
     * @return string 'http'|'https'
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * The lowercase host (ipv6 addresses are enclosed in brackets).
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * The port (the default port of the scheme when not specified).
     *
     * @return int|null
     */
    public function getPort()
    {
        if (null !== $this->port) {
            return $this->port;
        }

        return $this->getDefaultPort();
    }

    /**
     * @return int|null
     */
    public function getDefaultPort()
    {
        if (true === isset(self::$defaultPorts[$this->scheme])) {
            return self::$defaultPorts[$this->scheme];
        }

        return null;
    }

    /**
     * The path, as sent by the client (percent-encoded), e.g. '/shop/module/pledg/notification'.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * The raw query string requested by the client, without the leading '?'.
     * - getQuery() returns the decoded parameters of $_GET: with url rewriting, they can differ
     *   (e.g. they contain the parameters added by a RewriteRule).
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * 'scheme://host[:port]' (the port is omitted when it is the default one).
     *
     * @return string
     */
    public function getHostUrl()
    {
        $hostUrl = $this->scheme . '://' . $this->host;

        $port = $this->getPort();

        if (
            null !== $port
            && $port !== $this->getDefaultPort()
        ) {
            $hostUrl .= ':' . $port;
        }

        return $hostUrl;
    }

    /**
     * 'scheme://host[:port]/path[?query]'.
     *
     * @return string
     */
    public function getAbsoluteUrl()
    {
        $absoluteUrl = $this->getHostUrl() . $this->path;

        if ('' !== $this->queryString) {
            $absoluteUrl .= '?' . $this->queryString;
        }

        return $absoluteUrl;
    }

    /**
     * The part of the path matching the executed script (e.g. '/shop/index.php', or '/shop/' with url rewriting).
     *
     * @return string
     */
    public function getScriptPath()
    {
        return $this->scriptPath;
    }

    /**
     * The directory of the application, e.g. '/shop/'.
     *
     * @return string
     */
    public function getBasePath()
    {
        $pos = strrpos($this->scriptPath, '/');

        if (false === $pos) {
            return '';
        }

        return (string)substr($this->path, 0, $pos + 1);
    }

    /**
     * 'scheme://host[:port]/base/path/', e.g. 'https://example.com/shop/'.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->getHostUrl() . $this->getBasePath();
    }

    /**
     * The path relative to the base path, e.g. 'module/pledg/notification'.
     *
     * @return string
     */
    public function getRelativePath()
    {
        return (string)substr($this->path, \strlen($this->getBasePath()));
    }

    /**
     * The part of the path after the script path, e.g. '/notification' for '/shop/index.php/notification'.
     *
     * @return string
     */
    public function getPathInfo()
    {
        return (string)substr($this->path, \strlen($this->scriptPath));
    }

    /**
     * @param array<int|string, mixed> $values
     * @param int|string|null          $key
     * @param mixed|null               $default
     *
     * @return mixed|null
     */
    private function getValue(
        array $values,
        $key = null,
        $default = null
    ) {
        if (null === $key) {
            return $values;
        }

        if (true === array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default;
    }
}
