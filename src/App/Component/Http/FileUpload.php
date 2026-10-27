<?php

namespace Ecard\Cms\App\Component\Http;

use Ecard\Cms\App\Exception\HttpException;
use Exception;
use finfo;

/**
 * One uploaded file (an entry of $_FILES), inspired by Nette\Http\FileUpload.
 * - the "untrusted" values are sent by the client: never use them as they are
 *   (use getSanitizedName() and getContentType() instead).
 * - only a file which php has received as an upload (is_uploaded_file()) can be read or moved: the 'tmp_name' of
 *   a $_FILES-like array given to RequestFactory::create() could be forged (e.g. '/etc/passwd').
 *   Code which builds a FileUpload around its own file (tests, cli...) must opt in with
 *   FileUpload::ALLOW_NON_UPLOADED_FILES.
 */
final class FileUpload
{
    /**
     * second argument of the constructor: allows the file to be read and moved even if php did not receive it
     * as an upload. Never use it with a path coming from the client.
     */
    const ALLOW_NON_UPLOADED_FILES = true;

    /** @var array<string, string> mime type => extension (finfo FILEINFO_EXTENSION needs php >= 7.2) */
    private static $extensions = [
        'application/json' => 'json',
        'application/msword' => 'doc',
        'application/pdf' => 'pdf',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/xml' => 'xml',
        'application/zip' => 'zip',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/tiff' => 'tiff',
        'image/webp' => 'webp',
        'text/csv' => 'csv',
        'text/plain' => 'txt',
        'text/xml' => 'xml',
    ];

    /** @var array<int, string> image types accepted by isImage() (same list as nette) */
    private static $imageTypes = [
        'image/gif',
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/avif',
    ];

    /** @var string */
    private $name;

    /** @var string|null */
    private $fullPath;

    /** @var string */
    private $type;

    /** @var int */
    private $size;

    /** @var string */
    private $tmpName;

    /** @var int */
    private $error;

    /** @var string|null */
    private $contentType;

    /** @var bool */
    private $contentTypeDetected = false;

    /** @var bool */
    private $allowNonUploadedFiles;

    /** @var bool true once the file has been moved by move_uploaded_file(): it is then known to be an upload */
    private $verified = false;

    /**
     * @param array<string, mixed> $value                 an entry of $_FILES ('name', 'full_path', 'type', 'size', 'tmp_name', 'error')
     * @param bool                 $allowNonUploadedFiles FileUpload::ALLOW_NON_UPLOADED_FILES to read and move a file
     *                                                    which is not an upload (tests, cli...), false by default
     *
     * @return void
     */
    public function __construct(
        array $value = [],
        $allowNonUploadedFiles = false
    ) {
        $this->allowNonUploadedFiles = (true === $allowNonUploadedFiles);
        $this->name = (true === isset($value['name']) && true === is_scalar($value['name'])) ? (string)$value['name'] : '';
        $this->fullPath = (true === isset($value['full_path']) && true === is_scalar($value['full_path'])) ? (string)$value['full_path'] : null;
        $this->type = (true === isset($value['type']) && true === is_scalar($value['type'])) ? (string)$value['type'] : '';
        $this->size = (true === isset($value['size']) && true === is_numeric($value['size'])) ? (int)$value['size'] : 0;
        $this->tmpName = (true === isset($value['tmp_name']) && true === is_scalar($value['tmp_name'])) ? (string)$value['tmp_name'] : '';
        $this->error = (true === isset($value['error']) && true === is_numeric($value['error'])) ? (int)$value['error'] : \UPLOAD_ERR_NO_FILE;
    }

    /**
     * The maximum size of an uploaded file allowed by the server, in bytes:
     * the smallest of the 'upload_max_filesize' (per file) and 'post_max_size' (whole request) php.ini settings.
     * - a file larger than 'upload_max_filesize' arrives with the UPLOAD_ERR_INI_SIZE error (isOk() is false).
     * - a request larger than 'post_max_size' arrives without any POST data nor file (see Request::isPostTooLarge()).
     * - useful to display the limit in a form, or as an upper bound of the $maxSize of moveToDirectory().
     *
     * @return int|null null if the server sets no limit
     */
    public static function getMaxUploadSize()
    {
        $sizes = [];

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $size = self::iniSizeToBytes(ini_get($setting));

            if (null !== $size) {
                $sizes[] = $size;
            }
        }

        return (true === empty($sizes)) ? null : min($sizes);
    }

    /**
     * Converts a php.ini size ('2M', '512K', '1G', '1048576') into bytes, like php < 8.2 does
     * (ini_parse_quantity() only exists since php 8.2):
     * - the leading integer is kept, then multiplied according to the last character (k, m or g, case insensitive),
     *   e.g. '1.5M' gives 1M, like php.
     * - 0, a negative value or an invalid value mean "no limit" for upload_max_filesize and post_max_size.
     *
     * @param string|int|false|null $value a value returned by ini_get()
     *
     * @return int|null null for "no limit"
     */
    public static function iniSizeToBytes(
        $value
    ) {
        if (
            true !== is_string($value)
            && true !== is_int($value)
        ) {
            return null;
        }

        $value = trim((string)$value);

        if (1 !== preg_match('/^[+-]?\d+/', $value, $matches)) {
            return null;
        }

        $bytes = (int)$matches[0];

        switch (strtolower(substr($value, -1))) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
                break;
        }

        return ($bytes > 0) ? $bytes : null;
    }

    /**
     * The original file name, as sent by the client (may contain anything, e.g. '../../etc/passwd').
     *
     * @return string
     */
    public function getUntrustedName()
    {
        return $this->name;
    }

    /**
     * The original relative path when a whole directory is uploaded (php >= 8.1 only), as sent by the client.
     *
     * @return string|null
     */
    public function getUntrustedFullPath()
    {
        return $this->fullPath;
    }

    /**
     * The mime type, as sent by the client.
     *
     * @return string
     */
    public function getUntrustedType()
    {
        return $this->type;
    }

    /**
     * A file name safe to be used on the filesystem:
     * - only ascii letters, digits, '.' and '-' are kept (no directory separators, no '..').
     * - images get the extension matching their real content.
     *
     * @return string
     */
    public function getSanitizedName()
    {
        $name = (string)preg_replace('/[^A-Za-z0-9.]+/', '-', $this->name);
        $name = (string)preg_replace('/-*\.[.-]*/', '.', $name);
        $name = trim($name, '.-');

        if ('' === $name) {
            $name = 'unknown';
        }

        if (true === $this->isImage()) {
            $name = (string)preg_replace('/\.[^.]+$/D', '', $name) . '.' . $this->getSuggestedExtension();
        }

        return $name;
    }

    /**
     * The mime type, detected from the file content (null if the upload is not ok, the file is not readable
     * (see the class description) or the type is unknown).
     *
     * @return string|null
     */
    public function getContentType()
    {
        // (nothing is cached for a file which is not readable: it can become readable after move())
        if (
            true !== $this->isOk()
            || true !== $this->isReadable()
        ) {
            return null;
        }

        if (true !== $this->contentTypeDetected) {
            $this->contentTypeDetected = true;

            if (true === is_file($this->tmpName)) {
                $finfo = new finfo(\FILEINFO_MIME_TYPE);
                $contentType = $finfo->file($this->tmpName);
                $this->contentType = (true === is_string($contentType) && '' !== $contentType) ? $contentType : null;
            }
        }

        return $this->contentType;
    }

    /**
     * The usual extension (without the dot) of the detected content type.
     *
     * @return string|null
     */
    public function getSuggestedExtension()
    {
        $contentType = $this->getContentType();

        if (
            null === $contentType
            || true !== isset(self::$extensions[$contentType])
        ) {
            return null;
        }

        return self::$extensions[$contentType];
    }

    /**
     * The size in bytes.
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * The path of the temporary file (or the new path after move()).
     *
     * @return string
     */
    public function getTemporaryFile()
    {
        return $this->tmpName;
    }

    /**
     * One of the UPLOAD_ERR_* constants.
     *
     * @return int
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Whether the file was uploaded successfully.
     *
     * @return bool
     */
    public function isOk()
    {
        return \UPLOAD_ERR_OK === $this->error;
    }

    /**
     * Whether the user has selected a file (even if the upload then failed).
     *
     * @return bool
     */
    public function hasFile()
    {
        return \UPLOAD_ERR_NO_FILE !== $this->error;
    }

    /**
     * Whether the content of the file is a gif, png, jpeg, webp or avif image.
     *
     * @return bool
     */
    public function isImage()
    {
        return true === \in_array($this->getContentType(), self::$imageTypes, true);
    }

    /**
     * [width, height] of an image (null if the file is not an image).
     *
     * @return array<int, int>|null
     */
    public function getImageSize()
    {
        if (true !== $this->isImage()) {
            return null;
        }

        $size = @getimagesize($this->tmpName);

        if (false === $size) {
            return null;
        }

        return [(int)$size[0], (int)$size[1]];
    }

    /**
     * The content of the file (null if the upload is not ok).
     * - throws an HttpException if the file is not an upload (see the class description).
     *
     * @return string|null
     */
    public function getContents()
    {
        if (true !== $this->isOk()) {
            return null;
        }

        if (true !== $this->isReadable()) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_NOT_UPLOADED);
        }

        $contents = @file_get_contents($this->tmpName);

        if (false === $contents) {
            throw new HttpException(HttpException::MSG_CANNOT_READ_UPLOADED_FILE);
        }

        return $contents;
    }

    /**
     * Moves the file to its destination (the directory is created if needed, an existing file is replaced).
     * - an existing destination file is only replaced once the move has succeeded: if the move fails,
     *   the existing file is left untouched.
     * - the file is first moved to a temporary file in the destination directory, then renamed to the
     *   destination (a rename in the same directory replaces the existing file in one step, whereas a move
     *   from another filesystem, like /tmp, is a copy that could leave a truncated destination file).
     * - if the final rename fails, the file is put back where it was (or, if that is impossible, it stays
     *   in the temporary file, see getTemporaryFile()).
     * - throws an HttpException if the file is not an upload (see the class description).
     *
     * @param string $dest the destination file path
     *
     * @return self
     */
    public function move(
        $dest = null
    ) {
        if (true === empty($dest)) {
            throw new HttpException(HttpException::MSG_MISSING_PARAMETER_DESTINATION);
        }

        if (true !== $this->isOk()) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_NOT_OK);
        }

        if (true !== $this->isReadable()) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_NOT_UPLOADED);
        }

        $dir = \dirname($dest);

        if (
            true !== is_dir($dir)
            && true !== @mkdir($dir, 0777, true)
            && true !== is_dir($dir)
        ) {
            throw new HttpException(HttpException::MSG_CANNOT_CREATE_DIRECTORY);
        }

        // 1) move the file next to its destination (the destination itself is not touched yet)
        $tmpDest = $dir . '/.' . basename($dest) . '.' . self::generateRandomToken() . '.tmp';

        // files coming from $_FILES are moved with move_uploaded_file(), the others (cli, tests, previous move()) are renamed
        // (isReadable() above guarantees that a file which is not an upload has been explicitly allowed or already moved)
        if (true === is_uploaded_file($this->tmpName)) {
            $moved = @move_uploaded_file($this->tmpName, $tmpDest);

            // the file is now at a path chosen by the application: it can be read, even if it is no more an "upload" for php
            $this->verified = $this->verified || true === $moved;
        } else {
            $moved = @rename($this->tmpName, $tmpDest);
        }

        if (true !== $moved) {
            // a failed copy (move from another filesystem) can leave a partial file
            if (true === is_file($tmpDest)) {
                @unlink($tmpDest);
            }

            throw new HttpException(HttpException::MSG_CANNOT_MOVE_UPLOADED_FILE);
        }

        // 2) replace the destination in one step (same directory, so same filesystem)
        if (true !== @rename($tmpDest, $dest)) {
            // put the file back where it was, so that the upload is not lost
            if (true !== @rename($tmpDest, $this->tmpName)) {
                $this->tmpName = $tmpDest;
            }

            throw new HttpException(HttpException::MSG_CANNOT_MOVE_UPLOADED_FILE);
        }

        $this->tmpName = $dest;

        return $this;
    }

    /**
     * Checks the file, then moves it to a directory under a generated, safe and unique name.
     * - the type is the one detected from the content (getContentType()), never the one sent by the client.
     * - the size is the real size of the temporary file.
     * - the name is '<sanitized name without extension and dots>-<random token>[.<extension>]' (the token is
     *   32 hexadecimal characters from a cryptographically secure source, so the name cannot be guessed).
     *   The extension comes from the detected type (getSuggestedExtension()), never from the client; there is
     *   no extension if the type is unknown to getSuggestedExtension() (so that a '.php' file can never be created).
     * - the directory is created if needed.
     * - the directory should be outside the web root (or not allowed to execute scripts).
     *
     * usage:
     *     $path = $upload->moveToDirectory($uploadDir, ['application/pdf', 'image/png'], 5 * 1024 * 1024);
     *
     * @param string             $dir          the destination directory
     * @param array<int, string> $allowedTypes allowed mime types (case insensitive), e.g. ['application/pdf']
     * @param int                $maxSize      the maximum size, in bytes
     *
     * @return string the path of the moved file
     */
    public function moveToDirectory(
        $dir,
        array $allowedTypes,
        $maxSize
    ) {
        if (
            true !== is_string($dir)
            || '' === trim($dir)
        ) {
            throw new HttpException(HttpException::MSG_MISSING_PARAMETER_DIRECTORY);
        }

        $types = [];
        foreach ($allowedTypes as $allowedType) {
            if (
                true !== is_string($allowedType)
                || '' === trim($allowedType)
            ) {
                throw new HttpException(HttpException::MSG_INVALID_PARAMETER_ALLOWED_TYPES);
            }
            $types[] = strtolower(trim($allowedType));
        }

        if (true === empty($types)) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_ALLOWED_TYPES);
        }

        if (
            true !== is_int($maxSize)
            || $maxSize < 1
        ) {
            throw new HttpException(HttpException::MSG_INVALID_PARAMETER_MAX_SIZE);
        }

        if (true !== $this->isOk()) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_NOT_OK);
        }

        // (checked before reading anything about the file, e.g. its size)
        if (true !== $this->isReadable()) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_NOT_UPLOADED);
        }

        // the real size of the file (the 'size' entry of a manually built FileUpload can be anything)
        $size = (true === is_file($this->tmpName)) ? @filesize($this->tmpName) : false;

        if (false === $size) {
            throw new HttpException(HttpException::MSG_CANNOT_READ_UPLOADED_FILE);
        }

        if ($size > $maxSize) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_TOO_LARGE);
        }

        $contentType = $this->getContentType();

        if (
            null === $contentType
            || true !== \in_array(strtolower($contentType), $types, true)
        ) {
            throw new HttpException(HttpException::MSG_UPLOADED_FILE_TYPE_NOT_ALLOWED);
        }

        $dest = rtrim($dir, '/\\') . '/' . $this->generateFileName();

        $this->move($dest);

        return $dest;
    }

    /**
     * Whether the file can be read or moved: php must have received it as an upload (a 'tmp_name' can be forged),
     * unless it has been explicitly allowed, or it has already been moved by move_uploaded_file().
     *
     * @return bool
     */
    private function isReadable()
    {
        return true === $this->allowNonUploadedFiles
            || true === $this->verified
            || true === is_uploaded_file($this->tmpName);
    }

    /**
     * 32 hexadecimal characters from a cryptographically secure source (128 bits):
     * random_bytes() (php >= 7.0), or openssl_random_pseudo_bytes() on php 5.6.
     * - uniqid() and mt_rand() are not used: they are predictable (time based / seedable).
     * - throws an HttpException if no secure source is available.
     *
     * @return string
     */
    private static function generateRandomToken()
    {
        if (true === \function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (Exception $e) {
                // no source of randomness: try openssl below
            }
        }

        if (true === \function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes(16, $strong);

            if (
                true === is_string($bytes)
                && 16 === \strlen($bytes)
                && true === $strong
            ) {
                return bin2hex($bytes);
            }
        }

        throw new HttpException(HttpException::MSG_CANNOT_GENERATE_RANDOM_FILE_NAME);
    }

    /**
     * '<sanitized name without extension and dots>-<random token>[.<extension of the detected type>]'.
     *
     * @return string
     */
    private function generateFileName()
    {
        $baseName = (string)preg_replace('/\.[^.]*$/D', '', $this->getSanitizedName());

        // no dot left in the base name: 'shell.php.pdf' must not give a name like 'shell.php.<...>'
        // (some apache configurations execute any file having '.php' among its extensions)
        $baseName = str_replace('.', '-', $baseName);
        $baseName = trim((string)substr($baseName, 0, 100), '-');

        if ('' === $baseName) {
            $baseName = 'file';
        }

        // unpredictable: the name of a stored document (identity card...) must not be guessable
        $fileName = $baseName . '-' . self::generateRandomToken();

        $extension = $this->getSuggestedExtension();

        if (null !== $extension) {
            $fileName .= '.' . $extension;
        }

        return $fileName;
    }
}
