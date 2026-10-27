<?php

namespace Ecard\Cms\App\Component\Http;

use DateTimeInterface;
use Ecard\Cms\App\Exception\HttpException;
use finfo;

/**
 * The outgoing http response, inspired by Nette\Http\Response.
 *
 * usage:
 *     // the response, built once by the first App::get() call:
 *     $response = $app->response;
 *
 *     $response->setCode(404)->setHeader('X-Request-Id', $id);
 *     $response->setCookie('cart', $cartId, 3600);
 *     $response->setExpiration(null); // no cache
 *
 *     $response->redirect('/checkout/success');         // same host only (protection against open redirects)
 *     $response->sendJson(['status' => 'ok'], 200);     // e.g. a notification endpoint
 *     $response->sendFile($invoicePath, 'invoice.pdf'); // download
 *
 * - the methods send the headers immediately (like header()): there is no send() method. They throw an
 *   HttpException when the output has already started (the headers cannot be sent anymore), instead of the
 *   php warning "Cannot modify header information".
 * - every value is validated before anything is sent: an invalid parameter throws an HttpException and leaves
 *   the response untouched (no header injection: names must be http tokens, values cannot contain CR, LF
 *   nor other control characters).
 * - redirect(), sendJson() and sendFile() never call exit: the cms (or the caller) ends the script, so that
 *   its own shutdown code still runs (e.g. exit after $app->response->redirect($url)).
 * - inside a cms which builds its own response object (symfony, laminas...), prefer the response of the cms:
 *   the headers sent here are merged with (or replaced by) the headers it sends.
 */
final class Response
{
    const SAME_SITE_STRICT = 'Strict';
    const SAME_SITE_LAX = 'Lax';
    const SAME_SITE_NONE = 'None';

    // http token (RFC 9110): header names, cookie names, charsets
    const REGEX_TOKEN = '/^[a-z0-9!#$%&\'*+.^_`|~-]+$/iD';
    // control characters forbidden in a header value: C0 (except tab) and DEL
    // (byte mode: values which are not valid utf-8 are allowed, CR and LF are rejected anyway)
    const REGEX_HEADER_VALUE_CONTROL_CHARS = '/[\x00-\x08\x0A-\x1F\x7F]/';
    const REGEX_MIME_TYPE = '~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~iD';
    // printable ascii characters, except the backslash (browsers read it as a "/": '/\evil.com' is '//evil.com')
    // (spaces, controls and non ascii characters must be percent-encoded)
    const REGEX_REDIRECT_URL = '/^[\x21-\x5B\x5D-\x7E]+$/D';
    const REGEX_URL_SCHEME = '/^[a-z][a-z0-9+.-]*:/i';
    const REGEX_ABSOLUTE_URL = '~^(https?)://([^/?#]*)~i';
    // printable ascii characters, except ";"
    const REGEX_COOKIE_PATH = '~^/[\x21-\x3A\x3C-\x7E]*$~D';
    const REGEX_COOKIE_DOMAIN = '/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)*$/iD';
    // C0 controls, DEL and C1 controls (byte mode, on a valid utf-8 string: C1 controls are \xC2\x80 to \xC2\x9F)
    const REGEX_FILE_NAME_CONTROL_CHARS = '/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/';
    // characters replaced in the ascii fallback of a file name (utf-8 mode: one "_" per non ascii character)
    const REGEX_FILE_NAME_NOT_ASCII = '/[^\x20-\x7E]|["%\\\\]/u';

    const DATE_FORMAT = 'D, d M Y H:i:s \G\M\T';
    const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

    /** @var array<string, int> */
    private static $defaultPorts = [
        'http' => 80,
        'https' => 443,
    ];

    /** @var array<int, int> */
    private static $redirectCodes = [301, 302, 303, 307, 308];

    /** @var Request */
    private $request;

    /** @var HeaderSender */
    private $sender;

    /** @var int */
    private $code = 200;

    /**
     * @param Request           $request the incoming request (default cookie path and secure flag, host of the redirects)
     * @param HeaderSender|null $sender  writes the response (null: the php functions, header(), echo...)
     *
     * @return void
     */
    public function __construct(
        Request $request,
        $sender = null
    ) {
        if (null === $sender) {
            $sender = new HeaderSender();
        }

        if (true !== ($sender instanceof HeaderSender)) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_HEADER_SENDER);
        }

        $this->request = $request;
        $this->sender = $sender;

        // (the code may have been set by the cms before)
        $code = $sender->getCode();

        if (null !== $code) {
            $this->code = $code;
        }
    }

    /*
     * status and headers
     */

    /**
     * @param int $code from 100 to 599
     *
     * @return self
     */
    public function setCode(
        $code
    ) {
        if (
            true !== is_int($code)
            || $code < 100
            || $code > 599
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_CODE);
        }

        $this->checkHeadersNotSent();
        $this->sender->sendCode($code);
        $this->code = $code;

        return $this;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Sets a header (replaces the headers with the same name).
     *
     * @param string                $name  case insensitive
     * @param string|int|float|null $value null: deletes the header
     *
     * @return self
     */
    public function setHeader(
        $name,
        $value
    ) {
        if (null === $value) {
            return $this->deleteHeader($name);
        }

        $header = $this->buildHeader($name, $value);

        $this->checkHeadersNotSent();
        $this->sender->sendHeader($header, true);

        return $this;
    }

    /**
     * Adds a header (keeps the headers with the same name).
     *
     * @param string           $name
     * @param string|int|float $value
     *
     * @return self
     */
    public function addHeader(
        $name,
        $value
    ) {
        $header = $this->buildHeader($name, $value);

        $this->checkHeadersNotSent();
        $this->sender->sendHeader($header, false);

        return $this;
    }

    /**
     * @param string $name case insensitive
     *
     * @return self
     */
    public function deleteHeader(
        $name
    ) {
        $this->checkHeaderName($name);

        $this->checkHeadersNotSent();
        $this->sender->removeHeader($name);

        return $this;
    }

    /**
     * Returns the first value of a header which will be sent.
     *
     * @param string      $name    case insensitive
     * @param string|null $default
     *
     * @return string|null
     */
    public function getHeader(
        $name,
        $default = null
    ) {
        $headers = $this->getHeaders();
        $name = strtolower($name);

        if (true === isset($headers[$name])) {
            return $headers[$name][0];
        }

        return $default;
    }

    /**
     * Returns the headers which will be sent (all the headers set by php, not only the ones set by this class).
     * - always empty in cli (php does not keep the headers).
     *
     * @return array<string, array<int, string>> lowercase header name => values
     */
    public function getHeaders()
    {
        $headers = [];

        foreach ($this->sender->getSentHeaders() as $header) {
            $parts = explode(':', $header, 2);
            $headers[strtolower(trim($parts[0]))][] = (true === isset($parts[1])) ? trim($parts[1]) : '';
        }

        return $headers;
    }

    /**
     * @param string      $type    mime type, e.g. 'text/html'
     * @param string|null $charset e.g. 'utf-8'
     *
     * @return self
     */
    public function setContentType(
        $type,
        $charset = null
    ) {
        if (
            true !== is_string($type)
            || 1 !== preg_match(self::REGEX_MIME_TYPE, $type)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_CONTENT_TYPE);
        }

        if (null !== $charset) {
            if (
                true !== is_string($charset)
                || 1 !== preg_match(self::REGEX_TOKEN, $charset)
            ) {
                throw new HttpException(HttpException::MSG_INVALID_PARAMETER_CHARSET);
            }

            $type .= '; charset=' . $charset;
        }

        return $this->setHeader('Content-Type', $type);
    }

    /**
     * Whether the headers have been sent (the response cannot be modified anymore).
     *
     * @return bool
     */
    public function isSent()
    {
        return $this->sender->isSent();
    }

    /*
     * redirect
     */

    /**
     * Redirects to another url (status code and Location header).
     * - relative urls ('/checkout', 'step-2', '?page=2') are always allowed.
     * - absolute urls ('https://...', '//...') must be http or https urls, and by default must be on the host
     *   (and port) of the request (protection against open redirects): the scheme may differ (e.g. http to
     *   https).
     * - urls containing spaces, controls, backslashes or non ascii characters are rejected (browsers strip or
     *   reinterpret them, e.g. '/\evil.com' is read as '//evil.com'): percent-encode them.
     * - the script is not ended (see the class description).
     *
     * @param string $url
     * @param int    $code          301, 302, 303, 307 or 308
     * @param bool   $allowExternal true: allows any http or https url (e.g. the payment page of a provider:
     *                              never with an url coming from the client)
     *
     * @return self
     */
    public function redirect(
        $url,
        $code = 302,
        $allowExternal = false
    ) {
        if (true !== \in_array($code, self::$redirectCodes, true)) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_REDIRECT_CODE);
        }

        $url = $this->validateRedirectUrl($url, true === $allowExternal);

        return $this->setCode($code)->setHeader('Location', $url);
    }

    /*
     * cache
     */

    /**
     * Sets the cache headers (Cache-Control, Expires, Pragma).
     *
     * @param int|DateTimeInterface|null $expire null or 0: no cache, number of seconds or date: cache until then
     *                                           (a date in the past means no cache)
     *
     * @return self
     */
    public function setExpiration(
        $expire
    ) {
        $timestamp = 0;

        if (
            null !== $expire
            && 0 !== $expire
        ) {
            $timestamp = $this->toTimestamp($expire);
        }

        $maxAge = $timestamp - time();

        if ($maxAge <= 0) {
            return $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', self::formatDate(0));
        }

        return $this->setHeader('Cache-Control', 'max-age=' . $maxAge)
            ->setHeader('Expires', self::formatDate($timestamp))
            ->deleteHeader('Pragma');
    }

    /*
     * cookies
     */

    /**
     * Sends a cookie (Set-Cookie header, built manually: the same on every php version, SameSite included).
     * - the value is percent-encoded (php decodes it in $_COOKIE).
     * - the "__Secure-" and "__Host-" name prefixes are checked like browsers do.
     *
     * @param string                     $name
     * @param string|int|float           $value
     * @param int|DateTimeInterface|null $expire   number of seconds or date, null: session cookie
     * @param string|null                $path     null: the base path of the request (e.g. '/shop/')
     * @param string|null                $domain   null: the host of the request only (subdomains excluded)
     * @param bool|null                  $secure   null: secure if the request was made over https
     * @param bool                       $httpOnly true: hidden from javascript
     * @param string|null                $sameSite Response::SAME_SITE_* (case insensitive), null: not sent
     *
     * @return self
     */
    public function setCookie(
        $name,
        $value,
        $expire = null,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = true,
        $sameSite = self::SAME_SITE_LAX
    ) {
        if (
            true !== is_string($value)
            && true !== is_int($value)
            && true !== is_float($value)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_COOKIE_VALUE);
        }

        $timestamp = (null === $expire) ? null : $this->toTimestamp($expire);

        return $this->sendCookie($name, (string)$value, $timestamp, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * Deletes a cookie (sends an expired cookie): the path and domain must be those of the cookie.
     *
     * @param string      $name
     * @param string|null $path     see setCookie()
     * @param string|null $domain   see setCookie()
     * @param bool|null   $secure   see setCookie()
     * @param bool        $httpOnly see setCookie()
     * @param string|null $sameSite see setCookie()
     *
     * @return self
     */
    public function deleteCookie(
        $name,
        $path = null,
        $domain = null,
        $secure = null,
        $httpOnly = true,
        $sameSite = self::SAME_SITE_LAX
    ) {
        return $this->sendCookie($name, '', 0, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /*
     * body
     */

    /**
     * Sends a json response: Content-Type header and json body.
     * - the data is encoded before anything is sent: a data which cannot be encoded (e.g. invalid utf-8)
     *   throws an HttpException and leaves the response untouched.
     * - the script is not ended (see the class description).
     *
     * @param mixed|null $data
     * @param int|null   $code null: keeps the current status code
     *
     * @return void
     */
    public function sendJson(
        $data,
        $code = null
    ) {
        $json = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        if (
            false === $json
            || \JSON_ERROR_NONE !== json_last_error()
        ) {
            throw new HttpException(HttpException::jsonEncodingError(json_last_error_msg()));
        }

        if (null !== $code) {
            $this->setCode($code);
        }

        $this->setContentType('application/json', 'utf-8')
            ->setHeader('X-Content-Type-Options', 'nosniff');

        $this->sender->output($json);
    }

    /**
     * Sends a file (download).
     * - never with a path coming from the client (path traversal).
     * - the download name is reduced to a file name (no directory), with an ascii fallback and its utf-8 version
     *   (Content-Disposition, RFC 6266).
     * - the file is streamed (not loaded in memory). The output buffers of the cms, if any, are not cleaned.
     * - the script is not ended (see the class description).
     *
     * @param string      $path
     * @param string|null $downloadName null: the name of the file
     * @param string|null $contentType  null: detected from the content of the file
     * @param bool        $inline       true: displayed by the browser (only for trusted files: an html or svg file
     *                                  would run its scripts), false: downloaded
     *
     * @return void
     */
    public function sendFile(
        $path,
        $downloadName = null,
        $contentType = null,
        $inline = false
    ) {
        if (
            true !== is_string($path)
            || true !== is_file($path)
            || true !== is_readable($path)
        ) {
            throw new HttpException(HttpException::MSG_CANNOT_READ_FILE);
        }

        $size = filesize($path);

        if (false === $size) {
            throw new HttpException(HttpException::MSG_CANNOT_READ_FILE);
        }

        if (null === $downloadName) {
            $downloadName = $path;
        } elseif (true !== is_string($downloadName)) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_DOWNLOAD_NAME);
        }

        if (null === $contentType) {
            $contentType = $this->detectContentType($path);
        }

        $this->setContentType($contentType)
            ->setHeader('Content-Disposition', $this->buildContentDisposition($downloadName, true === $inline))
            ->setHeader('Content-Length', $size)
            ->setHeader('X-Content-Type-Options', 'nosniff');

        if (true !== $this->sender->outputFile($path)) {
            throw new HttpException(HttpException::MSG_CANNOT_READ_FILE);
        }
    }

    /**
     * @param int $timestamp
     *
     * @return string the date format of http headers (RFC 9110), e.g. 'Sun, 06 Nov 1994 08:49:37 GMT'
     */
    private static function formatDate(
        $timestamp
    ) {
        return gmdate(self::DATE_FORMAT, $timestamp);
    }

    /**
     * @return void
     */
    private function checkHeadersNotSent()
    {
        if (true === $this->sender->isSent()) {
            $location = $this->sender->getSentLocation();

            throw new HttpException(HttpException::headersAlreadySent($location[0], $location[1]));
        }
    }

    /**
     * @param string $name
     *
     * @return void
     */
    private function checkHeaderName(
        $name
    ) {
        if (
            true !== is_string($name)
            || 1 !== preg_match(self::REGEX_TOKEN, $name)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_HEADER_NAME);
        }
    }

    /**
     * @param string           $name
     * @param string|int|float $value
     *
     * @return string 'Name: value'
     */
    private function buildHeader(
        $name,
        $value
    ) {
        $this->checkHeaderName($name);

        if (
            true !== is_string($value)
            && true !== is_int($value)
            && true !== is_float($value)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_HEADER_VALUE);
        }

        $value = (string)$value;

        if (0 !== preg_match(self::REGEX_HEADER_VALUE_CONTROL_CHARS, $value)) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_HEADER_VALUE);
        }

        return $name . ': ' . $value;
    }

    /**
     * @param int|DateTimeInterface $expire number of seconds (from now) or date
     *
     * @return int
     */
    private function toTimestamp(
        $expire
    ) {
        if ($expire instanceof DateTimeInterface) {
            return $expire->getTimestamp();
        }

        if (
            true === is_int($expire)
            && $expire >= 0
        ) {
            return time() + $expire;
        }

        throw new HttpException(HttpException::MSG_INVALID_PARAMETER_EXPIRATION);
    }

    /**
     * @param string $url
     * @param bool   $allowExternal
     *
     * @return string the url to send ('//host/path' urls get the scheme of the request)
     */
    private function validateRedirectUrl(
        $url,
        $allowExternal
    ) {
        if (
            true !== is_string($url)
            || 1 !== preg_match(self::REGEX_REDIRECT_URL, $url)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_REDIRECT_URL);
        }

        if (0 === strpos($url, '//')) {
            $url = $this->request->getScheme() . ':' . $url;
        } elseif (1 !== preg_match(self::REGEX_URL_SCHEME, $url)) {
            // a relative url ('/path', 'path', '?query', '#fragment'): always on the host of the request
            return $url;
        }

        // the authority must be a valid host, with an optional port (no user info: 'https://shop@evil.com')
        if (
            1 !== preg_match(self::REGEX_ABSOLUTE_URL, $url, $matches)
            || 1 !== preg_match(RequestFactory::REGEX_HOST, $matches[2], $hostMatches)
            || (true === isset($hostMatches[2]) && ((int)$hostMatches[2] < 1 || (int)$hostMatches[2] > 65535))
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_REDIRECT_URL);
        }

        if (true === $allowExternal) {
            return $url;
        }

        // same host and port (the default port of each scheme is ignored, so that http can redirect to https)
        $scheme = strtolower($matches[1]);
        $host = rtrim(strtolower($hostMatches[1]), '.');
        $port = (true === isset($hostMatches[2])) ? (int)$hostMatches[2] : null;

        if ($port === self::$defaultPorts[$scheme]) {
            $port = null;
        }

        $requestPort = $this->request->getPort();

        if ($requestPort === $this->request->getDefaultPort()) {
            $requestPort = null;
        }

        if (
            $host !== rtrim($this->request->getHost(), '.')
            || $port !== $requestPort
        ) {
            throw new HttpException(HttpException::MSG_UNALLOWED_EXTERNAL_REDIRECT);
        }

        return $url;
    }

    /**
     * @param string      $name
     * @param string      $value     raw value (percent-encoded here)
     * @param int|null    $timestamp expiration date, null: session cookie
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null   $secure
     * @param bool        $httpOnly
     * @param string|null $sameSite
     *
     * @return self
     */
    private function sendCookie(
        $name,
        $value,
        $timestamp,
        $path,
        $domain,
        $secure,
        $httpOnly,
        $sameSite
    ) {
        if (
            true !== is_string($name)
            || 1 !== preg_match(self::REGEX_TOKEN, $name)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_COOKIE_NAME);
        }

        if (null === $path) {
            $path = $this->request->getBasePath();
            $path = ('' === $path) ? '/' : $path;
        }

        if (
            true !== is_string($path)
            || 1 !== preg_match(self::REGEX_COOKIE_PATH, $path)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_COOKIE_PATH);
        }

        if (null !== $domain) {
            // (a leading dot is ignored by browsers: '.example.com' is 'example.com')
            if (
                true !== is_string($domain)
                || 1 !== preg_match(self::REGEX_COOKIE_DOMAIN, ltrim($domain, '.'))
            ) {
                throw new HttpException(HttpException::MSG_INVALID_PARAMETER_COOKIE_DOMAIN);
            }

            $domain = strtolower(ltrim($domain, '.'));
        }

        $secure = (null === $secure) ? $this->request->isSecured() : (true === $secure);
        $sameSite = $this->normalizeSameSite($sameSite);

        if (
            true !== $secure
            && (
                self::SAME_SITE_NONE === $sameSite
                || 0 === stripos($name, '__Secure-')
                || 0 === stripos($name, '__Host-')
            )
        ) {
            throw new HttpException(HttpException::MSG_INSECURE_COOKIE);
        }

        if (
            0 === stripos($name, '__Host-')
            && ('/' !== $path || null !== $domain)
        ) {
            throw new HttpException(HttpException::MSG_INVALID_HOST_COOKIE);
        }

        $cookie = $name . '=' . rawurlencode($value);

        if (null !== $timestamp) {
            $cookie .= '; Expires=' . self::formatDate($timestamp) . '; Max-Age=' . max(0, $timestamp - time());
        }

        $cookie .= '; Path=' . $path;

        if (null !== $domain) {
            $cookie .= '; Domain=' . $domain;
        }

        if (true === $secure) {
            $cookie .= '; Secure';
        }

        if (true === $httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if (null !== $sameSite) {
            $cookie .= '; SameSite=' . $sameSite;
        }

        $this->checkHeadersNotSent();
        $this->sender->sendHeader('Set-Cookie: ' . $cookie, false);

        return $this;
    }

    /**
     * @param string|null $sameSite case insensitive
     *
     * @return string|null Response::SAME_SITE_*
     */
    private function normalizeSameSite(
        $sameSite
    ) {
        if (null === $sameSite) {
            return null;
        }

        $values = [self::SAME_SITE_STRICT, self::SAME_SITE_LAX, self::SAME_SITE_NONE];

        foreach ($values as $value) {
            if (
                true === is_string($sameSite)
                && 0 === strcasecmp($value, $sameSite)
            ) {
                return $value;
            }
        }

        throw new HttpException(HttpException::MSG_INVALID_PARAMETER_SAME_SITE);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function detectContentType(
        $path
    ) {
        $finfo = new finfo(\FILEINFO_MIME_TYPE);
        $type = $finfo->file($path);

        if (
            true !== is_string($type)
            || 1 !== preg_match(self::REGEX_MIME_TYPE, $type)
        ) {
            return self::DEFAULT_CONTENT_TYPE;
        }

        return $type;
    }

    /**
     * @param string $name   download name (a path is reduced to its file name)
     * @param bool   $inline
     *
     * @return string e.g. 'attachment; filename="facture_.pdf"; filename*=UTF-8\'\'facture%C3%A9.pdf'
     */
    private function buildContentDisposition(
        $name,
        $inline
    ) {
        // file name only ('\' is a directory separator on windows)
        $name = strtr($name, '\\', '/');
        $pos = strrpos($name, '/');
        $name = (false === $pos) ? $name : (string)substr($name, $pos + 1);

        // (an invalid utf-8 name is reduced to its ascii characters)
        if (true !== mb_check_encoding($name, 'UTF-8')) {
            $name = (string)preg_replace('/[^\x00-\x7F]/', '', $name);
        }

        $name = trim((string)preg_replace(self::REGEX_FILE_NAME_CONTROL_CHARS, '', $name));

        if (
            '' === $name
            || '.' === $name
            || '..' === $name
        ) {
            $name = 'download';
        }

        $fallback = (string)preg_replace(self::REGEX_FILE_NAME_NOT_ASCII, '_', $name);
        $disposition = ((true === $inline) ? 'inline' : 'attachment') . '; filename="' . $fallback . '"';

        if ($fallback !== $name) {
            $disposition .= '; filename*=UTF-8\'\'' . rawurlencode($name);
        }

        return $disposition;
    }
}
