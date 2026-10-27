<?php

namespace Ecard\Cms\App\Component\Http;

use Closure;
use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Exception\HttpException;

/**
 * Creates Request objects from the current incoming http request.
 *
 * usage:
 *     // the incoming request, built once with $app->request_factory by the first App::get() call:
 *     $request = $app->request;
 *     $request->getMethod(); $request->getAbsoluteUrl(); $request->getPost('amount'); ...
 *
 *     // with a specific configuration (the shared $app->request_factory is not modified):
 *     $request = $app->request_factory->withForceHttps()->withBinary()->fromGlobals();
 *
 * - GET, POST and cookie values (and uploaded file names) are cleansed: entries with invalid utf-8
 *   are removed, control characters (C0 except \t, \n and \r, DEL, and C1: U+0080 to U+009F) are stripped
 *   (see withBinary()).
 * - headers are always cleansed: headers with an invalid name or an invalid utf-8 value are removed,
 *   control characters (C0 with CR and LF included but tab kept, DEL, C1) are stripped, surrounding spaces
 *   and tabs are trimmed.
 * - $_FILES is normalized into FileUpload objects (empty file inputs are ignored).
 * - the X-Forwarded-For/Proto/Host/Port headers can be sent by anyone: they are only used when the request
 *   comes from a trusted proxy (see the 'trusted_proxies' app parameter or withTrustedProxies()).
 *
 * configuration:
 * - the factory is immutable: the with*() methods return a configured copy, they never modify the factory
 *   they are called on. So a configuration can neither leak into other parts of the code which use
 *   $app->request_factory, nor be lost when App::get() rebuilds $app->request_factory.
 * - the 'trusted_proxies' and 'trusted_hosts' app parameters configure $app->request_factory itself: a list,
 *   or a string of values separated by commas or whitespace (e.g. the field of a cms settings page).
 *   Invalid entries of these app parameters are ignored (an exception would break every App::get() call),
 *   getIgnoredTrustedProxies() / getIgnoredTrustedHosts() return them (e.g. to display a warning).
 *   Validate the settings when they are saved, with withTrustedProxies() / withTrustedHosts() which throw
 *   an HttpException for any invalid entry.
 *
 * trusted proxies:
 * - by default (no 'trusted_proxies' app parameter, or an empty one), no proxy is trusted:
 *   the X-Forwarded-* headers are ignored and only $_SERVER is used. This is safe (nothing can be spoofed),
 *   but behind a reverse proxy (cloudflare, load balancer...) getRemoteAddress() returns the ip of the proxy,
 *   and the scheme/port may be the internal ones (withForceHttps() fixes the scheme).
 *
 * trusted hosts:
 * - the host of the request comes from the Host header (or X-Forwarded-Host from a trusted proxy), which is
 *   sent by the client: without trusted hosts, getHost(), getAbsoluteUrl(), getBaseUrl()... can return
 *   any domain and port (host header poisoning: never use them to build links sent by email, notification
 *   urls...).
 * - trusted hosts are exact hosts ('shop.example.com') or wildcards ('*.example.com', which match the
 *   subdomains only), optionally with a port ('shop.example.com:8443'). Urls are accepted and reduced to
 *   their host and port ('https://shop.example.com/' gives 'shop.example.com'), a trailing dot is removed.
 * - the port must be the default port of the scheme (80 for http, 443 for https), or a port listed with
 *   the host: 'shop.example.com' alone does not allow 'shop.example.com:8080'. (this also applies to the port
 *   coming from SERVER_PORT, which apache sets from the Host header with 'UseCanonicalName Off': a port
 *   which is not allowed is dropped, so the url gets the default port).
 * - a host (or port) which is not trusted is ignored, and the first exact trusted host is used instead
 *   ('localhost' if there are only wildcards): Request::isHostTrusted() then returns false
 *   (e.g. to answer with a 400 Bad Request).
 * - fail closed: when the 'trusted_hosts' app parameter is not empty but none of its entries is valid,
 *   no host is trusted (every url gets 'localhost'), it never falls back to "any host".
 */
final class RequestFactory extends Component
{
    // (labels are not empty, a trailing dot is allowed: 'shop.example.com.' is a fully qualified domain name)
    const REGEX_HOST = '/^([a-z0-9_-]+(?:\.[a-z0-9_-]+)*\.?|\[[a-f0-9:.]+\])(?::(\d{1,5}))?$/iD';
    const REGEX_TRUSTED_HOST = '/^((?:\*\.)?[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\.?|\[[a-f0-9:.]+\])(?::(\d{1,5}))?$/D';
    const REGEX_URL_SCHEME = '#^[a-z][a-z0-9+.-]*://#i';
    const REGEX_LIST_SEPARATORS = '/[\s,]+/';
    const REGEX_METHOD = '/^[A-Z]+$/D';
    // C0 controls (except \t, \n, \r), DEL and C1 controls (U+0080 to U+009F)
    // (utf-8 mode, so that C1 controls are matched as characters, not as the continuation bytes of other
    // characters: only use it on a valid utf-8 string, preg_replace() returns null otherwise)
    const REGEX_CONTROL_CHARS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{80}-\x{9F}]/u';
    // (byte mode: the path and query string are cleansed before the utf-8 check, see cleanseUrlPart())
    const REGEX_URL_CONTROL_CHARS = '/[\x00-\x1F\x7F]/';
    const REGEX_HEADER_NAME = '/^[a-z0-9!#$%&\'*+.^_`|~-]+$/D';
    // C0 controls (except \t), DEL and C1 controls (utf-8 mode, same as REGEX_CONTROL_CHARS)
    const REGEX_HEADER_CONTROL_CHARS = '/[\x00-\x08\x0A-\x1F\x7F\x{80}-\x{9F}]/u';

    /** @var array<int, string> trusted proxies (ip addresses or cidr ranges) */
    private $proxies = [];

    /** @var array<int, mixed> invalid entries of the 'trusted_proxies' app parameter */
    private $ignoredProxies = [];

    /** @var array<int, string> trusted hosts, normalized: lowercase 'host' or 'host:port' ('*.domain' wildcards, ipv6 addresses enclosed in brackets) */
    private $trustedHosts = [];

    /** @var bool whether the hosts are restricted (a non empty trusted hosts setting, even without any valid entry) */
    private $hostsRestricted = false;

    /** @var array<int, mixed> invalid entries of the 'trusted_hosts' app parameter */
    private $ignoredHosts = [];

    /** @var bool */
    private $binary = false;

    /** @var bool */
    private $forceHttps = false;

    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);

        // (invalid entries are ignored: throwing here would break every App::get() call, see the class description)
        $proxies = $this->parseTrustedProxies($this->getAppParameter('trusted_proxies'), false);
        $this->proxies = $proxies[0];
        $this->ignoredProxies = $proxies[1];

        $hosts = $this->parseTrustedHosts($this->getAppParameter('trusted_hosts'), false);
        $this->trustedHosts = $hosts[0];
        $this->ignoredHosts = $hosts[1];
        $this->hostsRestricted = $hosts[2];
    }

    /**
     * Returns a copy of the factory with the given trusted proxies (they replace the current ones).
     * - a list, or a string of values separated by commas or whitespace (e.g. '10.0.0.1, 192.168.0.0/16').
     * - empty values (null, '', whitespace only) are ignored: withTrustedProxies(''), withTrustedProxies(null)
     *   and withTrustedProxies([]) all mean "no trusted proxy" (e.g. an empty field of a cms settings page).
     * - an invalid value (hostname, invalid ip or range...) throws an HttpException
     *   (useful to validate a setting before saving it).
     *
     * @param string|array<int|string, mixed>|null $proxies ip addresses or cidr ranges (e.g. '10.0.0.1', '10.0.0.0/8', 'fd00::/8')
     *
     * @return self
     */
    public function withTrustedProxies(
        $proxies
    ) {
        $parsed = $this->parseTrustedProxies($proxies, true);

        $factory = clone $this;
        $factory->proxies = $parsed[0];
        $factory->ignoredProxies = [];

        return $factory;
    }

    /**
     * @return array<int, string>
     */
    public function getTrustedProxies()
    {
        return $this->proxies;
    }

    /**
     * The invalid entries of the 'trusted_proxies' app parameter, which have been ignored
     * (e.g. to warn the merchant that the setting is wrong). Always empty after withTrustedProxies().
     *
     * @return array<int, mixed> the entries as they were given
     */
    public function getIgnoredTrustedProxies()
    {
        return $this->ignoredProxies;
    }

    /**
     * Returns a copy of the factory with the given trusted hosts (they replace the current ones).
     * - a list, or a string of values separated by commas or whitespace (e.g. 'shop.example.com, *.example.com').
     * - hosts with an optional port ('shop.example.com:8443'), '*.example.com' matches the subdomains,
     *   not 'example.com'. Urls are reduced to their host and port ('https://shop.example.com/a' gives
     *   'shop.example.com').
     * - empty values are ignored (no trusted host means "any host").
     * - an invalid value throws an HttpException (useful to validate a setting before saving it).
     *
     * @param string|array<int|string, mixed>|null $hosts
     *
     * @return self
     */
    public function withTrustedHosts(
        $hosts
    ) {
        $parsed = $this->parseTrustedHosts($hosts, true);

        $factory = clone $this;
        $factory->trustedHosts = $parsed[0];
        $factory->ignoredHosts = [];
        $factory->hostsRestricted = $parsed[2];

        return $factory;
    }

    /**
     * The normalized trusted hosts (e.g. to save the normalized value of a setting).
     *
     * @return array<int, string> lowercase 'host' or 'host:port' (ipv6 addresses enclosed in brackets, '*.domain' wildcards)
     */
    public function getTrustedHosts()
    {
        return $this->trustedHosts;
    }

    /**
     * The invalid entries of the 'trusted_hosts' app parameter, which have been ignored
     * (e.g. to warn the merchant that the setting is wrong). Always empty after withTrustedHosts().
     *
     * @return array<int, mixed> the entries as they were given
     */
    public function getIgnoredTrustedHosts()
    {
        return $this->ignoredHosts;
    }

    /**
     * Returns a copy of the factory which does not cleanse the GET, POST and cookie values (binary data is kept
     * as it is). (the headers are always cleansed).
     *
     * @param bool $binary
     *
     * @return self
     */
    public function withBinary(
        $binary = true
    ) {
        $factory = clone $this;
        $factory->binary = (true === $binary);

        return $factory;
    }

    /**
     * Returns a copy of the factory which always considers the request as made over https
     * (e.g. behind a proxy which does not send X-Forwarded-Proto).
     *
     * @param bool $forceHttps
     *
     * @return self
     */
    public function withForceHttps(
        $forceHttps = true
    ) {
        $factory = clone $this;
        $factory->forceHttps = (true === $forceHttps);

        return $factory;
    }

    /**
     * Creates a Request from the php superglobals (the body is read from php://input on demand).
     *
     * @return Request
     */
    public function fromGlobals()
    {
        $rawBodyCallback = function () {
            $rawBody = @file_get_contents('php://input');

            return (false === $rawBody) ? null : $rawBody;
        };

        return $this->create(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $rawBodyCallback
        );
    }

    /**
     * Creates a Request from the given arrays (used by fromGlobals(), and useful for tests).
     *
     * @param array<int|string, mixed> $server          same format as $_SERVER
     * @param array<int|string, mixed> $get             same format as $_GET
     * @param array<int|string, mixed> $post            same format as $_POST
     * @param array<int|string, mixed> $cookies         same format as $_COOKIE
     * @param array<int|string, mixed> $files           same format as $_FILES
     * @param Closure|null             $rawBodyCallback returns the raw body (string|null)
     *
     * @return Request
     */
    public function create(
        array $server = [],
        array $get = [],
        array $post = [],
        array $cookies = [],
        array $files = [],
        $rawBodyCallback = null
    ) {
        $headers = $this->getHeaders($server);

        $remoteAddress = $this->getServerIp($server, 'REMOTE_ADDR');
        $isFromProxy = false;

        if (
            null !== $remoteAddress
            && true === $this->isTrustedProxy($remoteAddress)
        ) {
            $isFromProxy = true;
            $remoteAddress = $this->getForwardedFor($headers, $remoteAddress);
        }

        $method = $this->getMethod($server);

        // (computed before the cleansing, which can empty $post)
        $postTooLarge = $this->isPostTooLarge($method, $headers, $post, $files);

        if (true !== $this->binary) {
            $get = $this->cleanse($get);
            $post = $this->cleanse($post);
            $cookies = $this->cleanse($cookies);
        }

        $url = $this->getUrl($server, $headers, $isFromProxy);
        $hostTrusted = (true === $url['host_trusted']);
        unset($url['host_trusted']);

        return new Request(
            $url,
            $get,
            $post,
            $this->getFiles($files),
            $cookies,
            $headers,
            $method,
            $remoteAddress,
            $rawBodyCallback,
            $postTooLarge,
            $hostTrusted
        );
    }

    /**
     * @param array<int|string, mixed> $server
     *
     * @return array<string, string> lowercase header name => value
     */
    private function getHeaders(
        array $server
    ) {
        $headers = [];

        foreach ($server as $key => $value) {
            if (
                true !== is_string($key)
                || true !== is_scalar($value)
            ) {
                continue;
            }

            if (
                0 === strpos($key, 'HTTP_')
                && \strlen($key) > 5
            ) {
                $name = substr($key, 5);
            } elseif (true === \in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = $key;
            } else {
                continue;
            }

            $headers[strtolower(strtr($name, '_', '-'))] = (string)$value;
        }

        // the authorization header is often hidden by apache/fastcgi
        if (true !== isset($headers['authorization'])) {
            if (
                true === isset($server['REDIRECT_HTTP_AUTHORIZATION'])
                && true === is_scalar($server['REDIRECT_HTTP_AUTHORIZATION'])
            ) {
                $headers['authorization'] = (string)$server['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (
                true === isset($server['PHP_AUTH_USER'])
                && true === is_scalar($server['PHP_AUTH_USER'])
            ) {
                $password = (true === isset($server['PHP_AUTH_PW']) && true === is_scalar($server['PHP_AUTH_PW'])) ? (string)$server['PHP_AUTH_PW'] : '';
                $headers['authorization'] = 'Basic ' . base64_encode((string)$server['PHP_AUTH_USER'] . ':' . $password);
            } elseif (
                true === isset($server['PHP_AUTH_DIGEST'])
                && true === is_scalar($server['PHP_AUTH_DIGEST'])
            ) {
                $headers['authorization'] = (string)$server['PHP_AUTH_DIGEST'];
            }
        }

        return $this->cleanseHeaders($headers);
    }

    /**
     * Cleanses the headers (always, even in binary mode: a header value is never binary data):
     * - headers with an invalid name (not an http "token") or with an invalid utf-8 value are removed.
     * - control characters (C0, DEL and C1) are stripped from the values (tab kept only inside the value:
     *   CR and LF would allow to inject a header when a value is written in a response, or a line in a log file).
     * - spaces and tabs around the values are removed (they are not part of the value, see RFC 9110).
     *
     * @param array<string, string> $headers lowercase header name => raw value
     *
     * @return array<string, string>
     */
    private function cleanseHeaders(
        array $headers
    ) {
        $cleansed = [];

        foreach ($headers as $name => $value) {
            if (
                1 !== preg_match(self::REGEX_HEADER_NAME, $name)
                || true !== $this->isValidUtf8($value)
            ) {
                continue;
            }

            $value = (string)preg_replace(self::REGEX_HEADER_CONTROL_CHARS, '', $value);
            $cleansed[$name] = trim($value, " \t");
        }

        return $cleansed;
    }

    /**
     * Whether php has dropped the body of a POST request larger than 'post_max_size':
     * php then gives empty $_POST and $_FILES (and only emits a warning), the Content-Length header
     * is the only remaining clue.
     *
     * @param string                   $method
     * @param array<string, string>    $headers
     * @param array<int|string, mixed> $post
     * @param array<int|string, mixed> $files
     *
     * @return bool
     */
    private function isPostTooLarge(
        $method,
        array $headers,
        array $post,
        array $files
    ) {
        if (
            'POST' !== $method
            || true !== empty($post)
            || true !== empty($files)
            || true !== isset($headers['content-length'])
            || 1 !== preg_match('/^\d+$/D', trim($headers['content-length']))
        ) {
            return false;
        }

        $postMaxSize = FileUpload::iniSizeToBytes(ini_get('post_max_size'));

        // (compared as floats: a huge Content-Length does not fit in an int)
        return null !== $postMaxSize
            && (float)trim($headers['content-length']) > (float)$postMaxSize;
    }

    /**
     * @param array<int|string, mixed> $server
     *
     * @return string
     */
    private function getMethod(
        array $server
    ) {
        if (
            true !== isset($server['REQUEST_METHOD'])
            || true !== is_string($server['REQUEST_METHOD'])
        ) {
            return 'GET';
        }

        $method = strtoupper($server['REQUEST_METHOD']);

        return (1 === preg_match(self::REGEX_METHOD, $method)) ? $method : 'GET';
    }

    /**
     * @param array<int|string, mixed> $server
     * @param array<string, string>    $headers
     * @param bool                     $isFromProxy
     *
     * @return array<string, mixed> 'scheme', 'host', 'port', 'path', 'query', 'script_path', 'host_trusted'
     */
    private function getUrl(
        array $server,
        array $headers,
        $isFromProxy
    ) {
        // scheme:
        if (true === $this->forceHttps) {
            $scheme = 'https';
        } elseif (
            true === $isFromProxy
            && true === isset($headers['x-forwarded-proto'])
        ) {
            $scheme = ('https' === strtolower($this->getFirstValue($headers['x-forwarded-proto']))) ? 'https' : 'http';
        } elseif (
            true === isset($server['HTTPS'])
            && true === is_scalar($server['HTTPS'])
            && '' !== (string)$server['HTTPS']
            && 'off' !== strtolower((string)$server['HTTPS'])
        ) {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }

        // host and port:
        // (no list() / [] destructuring: php-cs-fixer would convert list() to the php >= 7.1 short syntax)
        $hostAndPort = $this->getHostAndPort($server, $headers, $isFromProxy, $scheme);
        $host = $hostAndPort[0];
        $port = $hostAndPort[1];

        if (
            true === $this->forceHttps
            && 80 === $port
        ) {
            $port = null;
        }

        // path and query string:
        $hasRequestUri = (true === isset($server['REQUEST_URI']) && true === is_string($server['REQUEST_URI']));
        $requestUri = (true === $hasRequestUri) ? $server['REQUEST_URI'] : '/';

        // absolute-form request target (e.g. 'GET http://host/path HTTP/1.1')
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://[^/?\#]*#i', $requestUri, $matches)) {
            $requestUri = (string)substr($requestUri, \strlen($matches[0]));
        }

        $uriParts = explode('?', $requestUri, 2);
        $path = (string)preg_replace('#/{2,}#', '/', '/' . ltrim($this->cleanseUrlPart($uriParts[0]), '/'));

        // the query string of the request target, as sent by the client: QUERY_STRING is only a fallback,
        // as url rewriting (e.g. 'RewriteRule ... index.php?fc=module&... [QSA]') replaces it with the rewritten one
        // (which would give an absolute url mixing the requested path with the rewritten query)
        if (true === $hasRequestUri) {
            $queryString = (true === isset($uriParts[1])) ? $uriParts[1] : '';
        } elseif (
            true === isset($server['QUERY_STRING'])
            && true === is_string($server['QUERY_STRING'])
        ) {
            $queryString = $server['QUERY_STRING'];
        } else {
            $queryString = '';
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $this->cleanseUrlPart($queryString),
            'script_path' => $this->getScriptPath($path, $server),
            'host_trusted' => $hostAndPort[2],
        ];
    }

    /**
     * @param array<int|string, mixed> $server
     * @param array<string, string>    $headers
     * @param bool                     $isFromProxy
     * @param string                   $scheme      'http'|'https'
     *
     * @return array{0: string, 1: int|null, 2: bool} host, port, whether the host requested by the client is trusted
     */
    private function getHostAndPort(
        array $server,
        array $headers,
        $isFromProxy,
        $scheme
    ) {
        $host = null;
        $port = null;
        $candidates = [];

        if (
            true === $isFromProxy
            && true === isset($headers['x-forwarded-host'])
        ) {
            $candidates[] = $this->getFirstValue($headers['x-forwarded-host']);
        }

        if (true === isset($headers['host'])) {
            $candidates[] = $headers['host'];
        }

        // the first candidate is the host requested by the client (X-Forwarded-Host, or the Host header):
        // the next ones come from the server configuration
        $hasRequestedHost = (true !== empty($candidates));

        if (
            true === isset($server['SERVER_NAME'])
            && true === is_string($server['SERVER_NAME'])
        ) {
            $candidates[] = $server['SERVER_NAME'];
        }

        $serverAddress = $this->getServerIp($server, 'SERVER_ADDR');

        if (null !== $serverAddress) {
            $candidates[] = $serverAddress;
        }

        $hostTrusted = true;

        // the first valid and trusted candidate wins (an invalid or untrusted Host header is ignored)
        // (SERVER_NAME is checked too: apache sets it from the Host header with 'UseCanonicalName Off')
        foreach ($candidates as $index => $candidate) {
            $candidate = trim($candidate);

            // raw ipv6 addresses must be enclosed in brackets
            if (false !== filter_var($candidate, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                $candidate = '[' . $candidate . ']';
            }

            if (1 === preg_match(self::REGEX_HOST, $candidate, $matches)) {
                // (a fully qualified domain name: 'shop.example.com.' is 'shop.example.com')
                $candidateHost = rtrim(strtolower($matches[1]), '.');
                // (an unmatched trailing optional group is absent from $matches)
                $candidatePort = (true === isset($matches[2])) ? (int)$matches[2] : null;

                if (
                    true === $this->isTrustedHost($candidateHost)
                    && (
                        null === $candidatePort
                        || true === $this->isTrustedPort($candidateHost, $candidatePort, $scheme)
                    )
                ) {
                    $host = $candidateHost;
                    $port = $candidatePort;
                    break;
                }
            }

            if (
                0 === $index
                && true === $hasRequestedHost
            ) {
                $hostTrusted = false;
            }
        }

        if (null === $host) {
            $host = $this->getDefaultHost();
        }

        if (null === $port) {
            $serverPort = (true === isset($server['SERVER_PORT']) && true === is_numeric($server['SERVER_PORT'])) ? (int)$server['SERVER_PORT'] : null;

            if (true === $isFromProxy) {
                // behind a proxy, the local SERVER_PORT is not the public one
                if (
                    true === isset($headers['x-forwarded-port'])
                    && 1 === preg_match('/^\d{1,5}$/D', $this->getFirstValue($headers['x-forwarded-port']))
                ) {
                    $port = (int)$this->getFirstValue($headers['x-forwarded-port']);
                } elseif (true !== isset($headers['x-forwarded-proto'])) {
                    $port = $serverPort;
                }
            } else {
                $port = $serverPort;
            }

            // (SERVER_PORT can come from the Host header, see the class description)
            if (
                null !== $port
                && true !== $this->isTrustedPort($host, $port, $scheme)
            ) {
                $port = null;
            }
        }

        if (
            null !== $port
            && (
                $port < 1
                || $port > 65535
            )
        ) {
            $port = null;
        }

        return [
            $host,
            $port,
            $hostTrusted,
        ];
    }

    /**
     * The part of the path matching the executed script (inspired by nette):
     * - '/shop/index.php' for '/shop/index.php' (or '/shop/index.php/path/info'),
     * - '/shop/' for '/shop/product/12' (url rewriting).
     * - the result is always a prefix of the (percent-encoded) path, e.g. '/my%20shop/' for '/my%20shop/product/12'.
     *
     * the path is percent-encoded (as sent by the client), whereas SCRIPT_NAME is decoded ('/my shop/index.php'):
     * they are compared segment by segment, each segment of the path being decoded first
     * (case insensitively, like nette).
     *
     * @param string                   $path
     * @param array<int|string, mixed> $server
     *
     * @return string
     */
    private function getScriptPath(
        $path,
        array $server
    ) {
        if ('cli-server' === \PHP_SAPI) {
            return '/';
        }

        $scriptName = (true === isset($server['SCRIPT_NAME']) && true === is_string($server['SCRIPT_NAME'])) ? $server['SCRIPT_NAME'] : '';

        if ('' === $scriptName) {
            return '/';
        }

        // (the path always begins with '/': its first segment is '')
        $pathSegments = explode('/', $path);
        $scriptSegments = explode('/', $scriptName);

        $pathCount = \count($pathSegments);
        $scriptCount = \count($scriptSegments);

        // number of leading segments matching
        $i = 0;
        while (
            $i < $pathCount
            && $i < $scriptCount
            && strtolower(rawurldecode($pathSegments[$i])) === strtolower($scriptSegments[$i])
        ) {
            $i++;
        }

        // the path is the script itself, or the script followed by a path info
        if ($i === $scriptCount) {
            return implode('/', \array_slice($pathSegments, 0, $i));
        }

        // url rewriting: the common directory (a matching last segment of the path is not a directory,
        // e.g. '/shop' for the script '/shop/index.php')
        $dirCount = min($i, $pathCount - 1);

        return implode('/', \array_slice($pathSegments, 0, $dirCount)) . '/';
    }

    /**
     * Resolves the client ip from the X-Forwarded-For header:
     * the list is read from right to left, the first ip which is not a trusted proxy is the client
     * (the leftmost entries can be forged by the client).
     *
     * @param array<string, string> $headers
     * @param string                $remoteAddress
     *
     * @return string
     */
    private function getForwardedFor(
        array $headers,
        $remoteAddress
    ) {
        if (true !== isset($headers['x-forwarded-for'])) {
            return $remoteAddress;
        }

        $ips = [];
        foreach (explode(',', $headers['x-forwarded-for']) as $ip) {
            $ip = trim($ip);

            if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
                // an invalid entry: everything on its left is untrustworthy
                $ips = [];
                continue;
            }
            $ips[] = $ip;
        }

        for ($i = \count($ips) - 1; $i >= 0; $i--) {
            if (true !== $this->isTrustedProxy($ips[$i])) {
                return $ips[$i];
            }
        }

        // every forwarded ip is a trusted proxy: the leftmost one is the client
        return (true === empty($ips)) ? $remoteAddress : $ips[0];
    }

    /**
     * @param array<int|string, mixed> $server
     * @param string                   $key
     *
     * @return string|null
     */
    private function getServerIp(
        array $server,
        $key
    ) {
        if (
            true !== isset($server[$key])
            || true !== is_string($server[$key])
            || false === filter_var($server[$key], \FILTER_VALIDATE_IP)
        ) {
            return null;
        }

        return $server[$key];
    }

    /**
     * @param string $list a comma separated list (e.g. 'https, http')
     *
     * @return string
     */
    private function getFirstValue(
        $list
    ) {
        $parts = explode(',', $list, 2);

        return trim($parts[0]);
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    private function getAppParameter(
        $name
    ) {
        return (true === isset($this->app->parameters->{$name})) ? $this->app->parameters->{$name} : null;
    }

    /**
     * Splits a list setting into its non empty items.
     *
     * @param mixed|null $value a list, a string of values separated by commas or whitespace, a stdClass (app parameters
     *                          are converted to stdClass objects, lists included) or null
     *
     * @return array<int, mixed> trimmed non empty strings, and the items which are not strings (invalid)
     */
    private function splitList(
        $value
    ) {
        if (true === is_object($value)) {
            $value = get_object_vars($value);
        }

        $items = [];

        foreach ((array)$value as $item) {
            if (null === $item) {
                continue;
            }

            if (true !== is_string($item)) {
                $items[] = $item;
                continue;
            }

            $parts = preg_split(self::REGEX_LIST_SEPARATORS, $item, -1, \PREG_SPLIT_NO_EMPTY);

            foreach ((false === $parts) ? [] : $parts as $part) {
                $items[] = $part;
            }
        }

        return $items;
    }

    /**
     * @param mixed|null $value  see splitList()
     * @param bool       $strict true: an invalid entry throws an HttpException, false: it is ignored
     *
     * @return array{0: array<int, string>, 1: array<int, mixed>} the valid entries, the ignored ones
     */
    private function parseTrustedProxies(
        $value,
        $strict
    ) {
        $proxies = [];
        $ignored = [];

        foreach ($this->splitList($value) as $item) {
            if (
                true === is_string($item)
                && true === $this->isValidIpOrRange($item)
            ) {
                $proxies[] = $item;
            } elseif (true === $strict) {
                throw new HttpException(HttpException::MSG_INVALID_PARAMETER_PROXY);
            } else {
                $ignored[] = $item;
            }
        }

        return [$proxies, $ignored];
    }

    /**
     * Returns [the valid entries, the ignored ones, whether the hosts are restricted]: the hosts are restricted
     * as soon as there is a non empty entry, even an invalid one (fail closed).
     *
     * @param mixed|null $value  see splitList()
     * @param bool       $strict true: an invalid entry throws an HttpException, false: it is ignored
     *
     * @return array{0: array<int, string>, 1: array<int, mixed>, 2: bool}
     */
    private function parseTrustedHosts(
        $value,
        $strict
    ) {
        $hosts = [];
        $ignored = [];
        $items = $this->splitList($value);

        foreach ($items as $item) {
            $host = $this->normalizeTrustedHost($item);

            if (null !== $host) {
                $hosts[] = $host;
            } elseif (true === $strict) {
                throw new HttpException(HttpException::MSG_INVALID_PARAMETER_TRUSTED_HOST);
            } else {
                $ignored[] = $item;
            }
        }

        return [$hosts, $ignored, true !== empty($items)];
    }

    /**
     * Normalizes an entry of the trusted hosts: lowercase, scheme, path, query and trailing dot removed,
     * raw ipv6 addresses enclosed in brackets (e.g. 'HTTPS://Shop.Example.com.:8443/a' gives
     * 'shop.example.com:8443').
     *
     * @param mixed $item an entry of the setting (anything: it comes from the app parameters)
     *
     * @return string|null 'host' or 'host:port', null if the entry is invalid
     */
    private function normalizeTrustedHost(
        $item
    ) {
        if (true !== is_string($item)) {
            return null;
        }

        $value = (string)preg_replace(self::REGEX_URL_SCHEME, '', strtolower(trim($item)));

        // path, query string or fragment
        $value = (string)substr($value, 0, strcspn($value, '/?#'));

        if (false !== filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            $value = '[' . $value . ']';
        }

        if (1 !== preg_match(self::REGEX_TRUSTED_HOST, $value, $matches)) {
            return null;
        }

        $host = rtrim($matches[1], '.');

        if (true !== isset($matches[2])) {
            return $host;
        }

        $port = (int)$matches[2];

        if (
            $port < 1
            || $port > 65535
        ) {
            return null;
        }

        return $host . ':' . $port;
    }

    /**
     * @param string $trustedHost a normalized trusted host ('host' or 'host:port')
     *
     * @return array{0: string, 1: int|null} host, port
     */
    private function splitTrustedHost(
        $trustedHost
    ) {
        $pos = strrpos($trustedHost, ':');

        // (no port: no ':' at all, or the ':' of an ipv6 address enclosed in brackets)
        if (
            false === $pos
            || ']' === substr($trustedHost, -1)
        ) {
            return [$trustedHost, null];
        }

        return [(string)substr($trustedHost, 0, $pos), (int)substr($trustedHost, $pos + 1)];
    }

    /**
     * Whether a trusted host matches a host (and a port).
     *
     * @param string   $host lowercase host (ipv6 addresses enclosed in brackets)
     * @param int|null $port null: any port (the host only is checked), int: a trusted host listed with this port
     *
     * @return bool
     */
    private function matchesTrustedHost(
        $host,
        $port
    ) {
        foreach ($this->trustedHosts as $trustedHost) {
            $parts = $this->splitTrustedHost($trustedHost);

            if (
                null !== $port
                && $port !== $parts[1]
            ) {
                continue;
            }

            if ($host === $parts[0]) {
                return true;
            }

            if (0 === strpos($parts[0], '*.')) {
                // '.example.com': a subdomain is required
                $suffix = (string)substr($parts[0], 1);

                if (
                    \strlen($host) > \strlen($suffix)
                    && $suffix === substr($host, -\strlen($suffix))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a host can be used (always true without trusted hosts, never true with a trusted hosts
     * setting without any valid entry).
     *
     * @param string $host lowercase host (ipv6 addresses enclosed in brackets)
     *
     * @return bool
     */
    private function isTrustedHost(
        $host
    ) {
        return true !== $this->hostsRestricted
            || true === $this->matchesTrustedHost($host, null);
    }

    /**
     * Whether a port can be used with a host: the default port of the scheme, or a port listed with the host
     * in the trusted hosts (always true without trusted hosts).
     *
     * @param string $host   lowercase host (ipv6 addresses enclosed in brackets)
     * @param int    $port
     * @param string $scheme 'http'|'https'
     *
     * @return bool
     */
    private function isTrustedPort(
        $host,
        $port,
        $scheme
    ) {
        return true !== $this->hostsRestricted
            || $port === (('https' === $scheme) ? 443 : 80)
            || true === $this->matchesTrustedHost($host, $port);
    }

    /**
     * The host used when no candidate host is valid (and trusted).
     *
     * @return string the first exact trusted host, 'localhost' if there is none
     */
    private function getDefaultHost()
    {
        foreach ($this->trustedHosts as $trustedHost) {
            if (0 !== strpos($trustedHost, '*.')) {
                $parts = $this->splitTrustedHost($trustedHost);

                return $parts[0];
            }
        }

        return 'localhost';
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    private function isTrustedProxy(
        $ip
    ) {
        foreach ($this->proxies as $proxy) {
            if (true === $this->ipMatches($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isValidIpOrRange(
        $value
    ) {
        $parts = explode('/', $value, 2);

        if (false === filter_var($parts[0], \FILTER_VALIDATE_IP)) {
            return false;
        }

        if (true !== isset($parts[1])) {
            return true;
        }

        $maxBits = (false !== strpos($parts[0], ':')) ? 128 : 32;

        return 1 === preg_match('/^\d{1,3}$/D', $parts[1])
            && (int)$parts[1] <= $maxBits;
    }

    /**
     * @param string $ip    an ip address
     * @param string $range an ip address or a cidr range
     *
     * @return bool
     */
    private function ipMatches(
        $ip,
        $range
    ) {
        $parts = explode('/', $range, 2);

        $ipBinary = @inet_pton($ip);
        $rangeBinary = @inet_pton($parts[0]);

        if (
            false === $ipBinary
            || false === $rangeBinary
            || \strlen($ipBinary) !== \strlen($rangeBinary)
        ) {
            return false;
        }

        $bits = (true === isset($parts[1])) ? (int)$parts[1] : \strlen($ipBinary) * 8;
        $bytes = (int)floor($bits / 8);

        if (substr($ipBinary, 0, $bytes) !== substr($rangeBinary, 0, $bytes)) {
            return false;
        }

        $remainingBits = $bits % 8;

        if (0 === $remainingBits) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (\ord($ipBinary[$bytes]) & $mask) === (\ord($rangeBinary[$bytes]) & $mask);
    }

    /**
     * Recursively removes the entries with invalid utf-8 (keys or values) or with a control character in their key,
     * and strips the control characters (C0 except \t, \n and \r, DEL, C1) from the values.
     * - the utf-8 check must come first: REGEX_CONTROL_CHARS is a utf-8 regex.
     *
     * @param array<int|string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    private function cleanse(
        array $values
    ) {
        $cleansed = [];

        foreach ($values as $key => $value) {
            if (
                true === is_string($key)
                && (
                    true !== $this->isValidUtf8($key)
                    || 1 === preg_match(self::REGEX_CONTROL_CHARS, $key)
                )
            ) {
                continue;
            }

            if (true === is_array($value)) {
                $cleansed[$key] = $this->cleanse($value);
            } elseif (true === is_string($value)) {
                if (true !== $this->isValidUtf8($value)) {
                    continue;
                }
                $cleansed[$key] = (string)preg_replace(self::REGEX_CONTROL_CHARS, '', $value);
            } else {
                $cleansed[$key] = $value;
            }
        }

        return $cleansed;
    }

    /**
     * Removes the control characters of a path or query string, and percent-encodes it if it is not valid utf-8.
     *
     * @param string $value
     *
     * @return string
     */
    private function cleanseUrlPart(
        $value
    ) {
        $value = (string)preg_replace(self::REGEX_URL_CONTROL_CHARS, '', $value);

        if (true !== $this->isValidUtf8($value)) {
            $value = (string)preg_replace_callback(
                '/[\x80-\xFF]/',
                function ($matches) {
                    return rawurlencode($matches[0]);
                },
                $value
            );
        }

        return $value;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isValidUtf8(
        $value
    ) {
        return 1 === preg_match('//u', $value);
    }

    /**
     * Normalizes a $_FILES like array into FileUpload objects
     * (the "transposed" structure php uses for nested fields like name="documents[identity]" is fixed).
     *
     * @param array<int|string, mixed> $files
     *
     * @return array<int|string, mixed>
     */
    private function getFiles(
        array $files
    ) {
        $normalized = [];

        foreach ($files as $field => $value) {
            if (
                true !== is_array($value)
                || true !== isset($value['name'])
            ) {
                continue;
            }

            $item = $this->normalizeFile($value);

            if (null !== $item) {
                $normalized[$field] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $value e.g. ['name' => 'a.pdf', ...] or ['name' => ['x' => 'a.pdf'], 'tmp_name' => ['x' => '/tmp/a'], ...]
     *
     * @return FileUpload|array<int|string, mixed>|null null for an empty file input
     */
    private function normalizeFile(
        array $value
    ) {
        if (true !== array_key_exists('name', $value)) {
            return null;
        }

        if (true !== is_array($value['name'])) {
            return $this->createFileUpload($value);
        }

        $normalized = [];

        foreach ($value['name'] as $key => $name) {
            $subValue = [];

            foreach (['name', 'full_path', 'type', 'size', 'tmp_name', 'error'] as $property) {
                if (
                    true === isset($value[$property])
                    && true === is_array($value[$property])
                    && true === array_key_exists($key, $value[$property])
                ) {
                    $subValue[$property] = $value[$property][$key];
                }
            }

            $item = $this->normalizeFile($subValue);

            if (
                null !== $item
                && [] !== $item
            ) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $value
     *
     * @return FileUpload|null null for an empty file input
     */
    private function createFileUpload(
        array $value
    ) {
        if (
            true !== isset($value['error'])
            || true !== is_numeric($value['error'])
            || \UPLOAD_ERR_NO_FILE === (int)$value['error']
        ) {
            return null;
        }

        $file = [];
        foreach ($value as $key => $item) {
            $file[(string)$key] = $item;
        }

        if (true !== $this->binary) {
            foreach (['name', 'full_path'] as $property) {
                if (
                    true === isset($file[$property])
                    && true === is_string($file[$property])
                ) {
                    $file[$property] = (true === $this->isValidUtf8($file[$property]))
                        ? (string)preg_replace(self::REGEX_CONTROL_CHARS, '', $file[$property])
                        : '';
                }
            }
        }

        return new FileUpload($file);
    }
}
