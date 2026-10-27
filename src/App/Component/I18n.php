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

        if (
            true === \property_exists($this->app, 'parameters')
            && true === \property_exists($this->app->parameters, 'locale')
            && true !== empty($this->app->parameters->locale)
            && true === \is_string($this->app->parameters->locale)
        ) {
            $locale = trim($this->app->parameters->locale);
            $this->locale = $this->getMatchingLocale($locale);
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

            foreach ($listResourceFiles as $filePath) {
                $fileName = basename($filePath);

                $parts = explode('.', $fileName);
                if (count($parts) >= 3) {
                    $domain = $parts[0];
                    $locale = $parts[1];

                    if (
                        true === $this->isValidLocale($locale)
                        && true === $this->isEnabledLocale($locale)
                        && true === $this->isEnabledDomain($domain)
                        && true === is_readable($filePath)
                        && true === is_file($filePath)
                    ) {
                        $this->translator->addResource(
                            $this->app->config->i18n->resources_format,
                            $filePath,
                            $locale,
                            $domain
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string                    $translationKey
     * @param array<string, mixed>|null $parameters
     * @param string                    $locale
     * @param string                    $domain
     *
     * @return string
     */
    public function t(
        $translationKey = null,
        $parameters = [],
        $locale = null,
        $domain = null
    ) {
        if (
            true === empty($translationKey)
            || true !== \is_string($translationKey)
            || true === empty(trim($translationKey))
        ) {
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
        $wrappedParameters = [];
        foreach ($parameters as $key => $value) {
            $cleanKey = trim($key, '%');
            $newKey = '%' . $cleanKey . '%';
            $wrappedParameters[$newKey] = $value;
        }

        $selectedLocale = $this->getMatchingLocale($locale);
        $translatedText = $this->translator->trans($translationKey, $wrappedParameters, $domain, $selectedLocale);

        return $translatedText;
    }

    /**
     * @param string $domain
     *
     * @return bool
     */
    private function isEnabledDomain(
        $domain = null
    ) {
        $ret = \in_array($domain, $this->app->config->i18n->enabled_domains, true);

        return $ret;
    }

    /**
     * @param string $locale
     *
     * @return bool
     */
    private function isValidLocale(
        $locale = null
    ) {
        if (
            true === empty($locale)
            || 1 !== preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)
        ) {
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
        $ret = \in_array($locale, $this->app->config->i18n->enabled_locales, true);

        return $ret;
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    public function getMatchingLocale(
        $locale = null
    ) {
        if (
            true === empty($locale)
            || true !== \is_string($locale)
        ) {
            return $this->locale;
        }

        $locale = str_replace('-', '_', $locale);

        if (
            true === $this->isValidLocale($locale)
            && true === $this->isEnabledLocale($locale)
        ) {
            return $locale;
        }

        // in case locale is not valid but is a language iso code
        $localeLang = substr($locale, 0, 2);

        return $this->getLocaleFromLang($localeLang);
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    private function getLocaleFromLang(
        $lang = null
    ) {
        if (true !== empty($lang)) {
            $lang = strtolower($lang);

            foreach ($this->app->config->i18n->enabled_locales as $enabledLocale) {
                $enabledLocaleLang = substr($enabledLocale, 0, 2);
                if ($enabledLocaleLang === $lang) {
                    return $enabledLocale;
                }
            }
        }

        // if we dont find any matching locale:
        return $this->locale;
    }
}
