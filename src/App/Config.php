<?php

namespace Ecard\Cms\App;

use Ecard\Cms\App\Exception\AppConfigException;
use stdClass;

final class Config
{
    /** @var string */
    private $folder = 'etc';

    /** @var string */
    private $path;

    /** @var stdClass */
    public $i18n;

    /** @var stdClass */
    public $http_client;

    /** @var stdClass */
    public $api;

    public function __construct()
    {
        $this->path = __DIR__ . '/../../' . $this->folder;
        $this->loadConfig();
    }

    /**
     * @return void
     */
    private function loadConfig()
    {
        $patternConfigFiles = $this->path . '/*/config.json';
        $listConfigFiles = glob($patternConfigFiles);

        if (true !== empty($listConfigFiles)) {
            foreach ($listConfigFiles as $filePath) {
                $this->loadJsonConfig($filePath);
            }
        }
    }

    /**
     * @param string $filePath
     *
     * @return void
     */
    private function loadJsonConfig(
        $filePath = null
    ) {
        $confName = null;
        $confData = null;

        if (
            true !== is_file($filePath)
            || true !== is_readable($filePath)
        ) {
            throw new AppConfigException(AppConfigException::MSG_CANNOT_ACCESS_CONFIG_FILE);
        }

        $parts = explode('/', $filePath);

        if (count($parts) >= 2) {
            $fileContent = file_get_contents($filePath);
            $confName = trim($parts[count($parts) - 2]);

            if (true !== empty($fileContent)) {
                $confData = json_decode($fileContent, false);

                if (JSON_ERROR_NONE !== json_last_error()) {
                    throw new AppConfigException(AppConfigException::MSG_CANNOT_DECODE_CONFIG_FILE);
                }
            }
        }

        if (true !== empty($confData)) {
            $this->createProperty($confName, $confData);
        }
    }

    /**
     * @param string   $name
     * @param stdClass $value
     *
     * @return void
     */
    private function createProperty(
        $name = null,
        $value = null
    ) {
        if (true !== empty($name)) {
            $this->{$name} = $value;
        }
    }
}
