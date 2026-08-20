<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

/**
 * Typed view-model for the application section of the Configuration panel.
 */
final readonly class ApplicationConfig
{
    public function __construct(
        /**
         * Yii framework version reported at request capture time.
         */
        public string $yii,
        /**
         * Configured {@see \yii\base\Application::$name}, or empty string when the application is unavailable.
         */
        public string $name,
        /**
         * Configured {@see \yii\base\Application::$version}, or empty string when none is set.
         */
        public string $version,
        /**
         * Configured {@see \yii\base\Application::$language} BCP-47 tag, or empty string.
         */
        public string $language,
        /**
         * Configured {@see \yii\base\Application::$sourceLanguage} BCP-47 tag, or empty string.
         */
        public string $sourceLanguage,
        /**
         * Configured {@see \yii\base\Application::$charset}, or empty string.
         */
        public string $charset,
        /**
         * Active {@see YII_ENV} environment label.
         */
        public string $env,
        /**
         * Active {@see YII_DEBUG} flag.
         */
        public bool $debug,
    ) {}
}
