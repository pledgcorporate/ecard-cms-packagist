<?php

namespace Ecard\Cms\App\Component\Http;

/**
 * Thin wrapper around the php functions which write the http response (header(), http_response_code(),
 * echo, readfile()...), used by Response.
 * - Response contains all the logic (validation, formatting): this class only calls the php functions,
 *   without any check.
 * - it is not final so that tests can replace it with a subclass which records the headers and the output
 *   (in cli, php never sends the headers: headers_list() is always empty).
 */
class HeaderSender
{
    /**
     * Whether the headers have been sent (the headers cannot be modified anymore).
     *
     * @return bool
     */
    public function isSent()
    {
        return headers_sent();
    }

    /**
     * Where the output has started, which has sent the headers.
     *
     * @return array{0: string, 1: int} [file, line] (['', 0] if the headers have not been sent)
     */
    public function getSentLocation()
    {
        headers_sent($file, $line);

        return [(string)$file, (int)$line];
    }

    /**
     * The status code of the response.
     *
     * @return int|null null if no status code has been set (cli)
     */
    public function getCode()
    {
        $code = http_response_code();

        return (true === is_int($code)) ? $code : null;
    }

    /**
     * @param int $code
     *
     * @return void
     */
    public function sendCode(
        $code
    ) {
        http_response_code($code);
    }

    /**
     * @param string $header  'Name: value'
     * @param bool   $replace true: replaces the headers with the same name, false: adds a header
     *
     * @return void
     */
    public function sendHeader(
        $header,
        $replace
    ) {
        header($header, $replace);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function removeHeader(
        $name
    ) {
        header_remove($name);
    }

    /**
     * The headers which will be sent (or have been sent).
     *
     * @return array<int, string> 'Name: value' strings
     */
    public function getSentHeaders()
    {
        return headers_list();
    }

    /**
     * @param string $content
     *
     * @return void
     */
    public function output(
        $content
    ) {
        echo $content;
    }

    /**
     * Writes the content of a file to the output (without loading it in memory).
     *
     * @param string $path
     *
     * @return bool false if the file cannot be read
     */
    public function outputFile(
        $path
    ) {
        return false !== @readfile($path);
    }
}
