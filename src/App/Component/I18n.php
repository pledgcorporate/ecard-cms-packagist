<?php

namespace Ecard\Cms\App\Component;

use Ecard\Cms\App;
use Ecard\Cms\App\Component;
use Ecard\Cms\App\Exception\I18nException;
use Ecard\Cms\Dependencies\Symfony\Component\Translation\Loader\JsonFileLoader;
use Ecard\Cms\Dependencies\Symfony\Component\Translation\Translator;

final class I18n extends Component
{
    /** @var string */
    private $locale;

    /** @var Translator */
    private $translator;

    /**
     * @param App $app
     *
     * @return void
     */
    public function __construct(
        $app = null
    ) {
        parent::__construct($app);

        $this->locale = $this->app->config->i18n->default_locale;

        $locale = null;
        if (
            true === \property_exists($this->app, 'parameters')
            && true === \property_exists($this->app->parameters, 'locale')
        ) {
            $locale = trim($this->app->parameters->locale);

            if (
                true !== empty($locale)
                && true === $this->isValidLocale($locale)
                && true === $this->isEnabledLocale($locale)
            ) {
                $this->locale = $locale;
            }
        }

        $this->setTranslator();
    }

    /**
     * @return void
     */
    private function setTranslator()
    {
        $this->translator = new Translator($this->locale);

        $this->translator->setFallbackLocales(
            [
                $this->app->config->i18n->default_locale,
            ]
        );

        $this->loadResources();

        $this->translator->setLocale($this->locale);
    }

    /**
     * @return void
     */
    private function loadResources()
    {
        $patternResourceFiles = __DIR__ . '/../../..' . $this->app->config->i18n->resources_dir . '/*.' . $this->app->config->i18n->resources_format;
        $listResourceFiles = glob($patternResourceFiles);

        if (
            is_array($listResourceFiles)
            && 0 < count($listResourceFiles)
        ) {
            $this->translator->addLoader($this->app->config->i18n->resources_format, new JsonFileLoader());

            for ($i = 0; $i < count($listResourceFiles); $i++) {
                $filePath = $listResourceFiles[$i];
                $fileName = basename($filePath);

                $parts = explode('.', $fileName);
                if (count($parts) >= 3) {
                    $domain = $parts[0];
                    $locale = $parts[1];
                    $format = $parts[2];

                    if (
                        true === $this->isValidLocale($locale)
                        && true === $this->isEnabledLocale($locale)
                        && true === $this->isEnabledDomain($domain)
                        && true === is_readable($filePath)
                        && true === is_file($filePath)
                    ) {
                        $this->translator->addResource($format, $filePath, $locale, $domain);
                    }
                }
            }
        }
    }

    /**
     * @param string               $translationKey
     * @param array<string, mixed> $parameters
     * @param string               $locale
     * @param string               $domain
     *
     * @return string
     */
    public function t(
        $translationKey = null,
        $parameters = [],
        $locale = null,
        $domain = null
    ) {
        if (true === empty($translationKey)) {
            throw new I18nException(I18nException::MSG_MISSING_PARAMETER_TRANSLATIONKEY);
        }
        if (
            true === empty($parameters)
            || true !== \is_array($parameters)
        ) {
            $parameters = [];
        }
        if (
            true === empty($domain)
            || true !== $this->isEnabledDomain($domain)
        ) {
            $domain = $this->app->config->i18n->default_domain;
        }

        // every parameter is surrounded by '%' caracters in translation strings
        foreach ($parameters as $key => $value) {
            $newKey = '%' . $key . '%';
            $parameters[$newKey] = $value;
            unset($parameters[$key]);
        }

        $selectedLocale = $this->getMatchingLocale($locale);

        return $this->translator->trans($translationKey, $parameters, $domain, $selectedLocale);
    }

    /**
     * @param string $domain
     *
     * @return bool
     */
    private function isEnabledDomain(
        $domain = null
    ) {
        if (true !== \in_array($domain, $this->app->config->i18n->enabled_domains, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $locale
     *
     * @return bool
     */
    private function isValidLocale(
        $locale = null
    ) {
        if (1 !== preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $locale
     *
     * @return bool
     */
    private function isEnabledLocale(
        $locale = null
    ) {
        if (true !== \in_array($locale, $this->app->config->i18n->enabled_locales, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    private function getMatchingLocale(
        $locale = null
    ) {
        if (
            true === empty($locale)
            || true !== $this->isValidLocale($locale)
        ) {
            return $this->locale;
        }

        if (true === $this->isEnabledLocale($locale)) {
            return $locale;
        }

        $localeLang = substr($locale, 0, 2);
        foreach ($this->app->config->i18n->enabled_locales as $enabledLocale) {
            $enabledLocaleLang = substr($enabledLocale, 0, 2);
            if ($enabledLocaleLang === $localeLang) {
                return $enabledLocale;
            }
        }

        return $this->app->config->i18n->default_locale;
    }
}
