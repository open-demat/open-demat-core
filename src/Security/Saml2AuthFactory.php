<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Metadata;
use OneLogin\Saml2\Settings;
use Symfony\Component\Filesystem\Path;

final class Saml2AuthFactory
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private readonly array $settings,
        private readonly string $projectDir,
        private readonly string $idpMetadataFile,
        private readonly string $idpMetadataUrl,
        private readonly string $idpMetadataXml,
    ) {
    }

    public function createAuth(): Auth
    {
        return new Auth($this->settingsWithIdpMetadata());
    }

    public function createSettings(bool $spValidationOnly = false): Settings
    {
        return new Settings($this->settingsWithIdpMetadata(), $spValidationOnly);
    }

    public function getSPMetadata(): string
    {
        $security = $this->settings['security'] ?? [];

        return Metadata::builder(
            $this->settings['sp'],
            $security['authnRequestsSigned'] ?? false,
            $security['wantAssertionsSigned'] ?? false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsWithIdpMetadata(): array
    {
        $settings = $this->settings;
        $metadata = $this->loadIdpMetadata();

        if ($metadata !== []) {
            $settings = array_replace_recursive($settings, $metadata);
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadIdpMetadata(): array
    {
        if ($this->idpMetadataXml !== '') {
            return IdPMetadataParser::parseXML(
                $this->idpMetadataXml,
                null,
                null,
                Constants::BINDING_HTTP_REDIRECT,
                Constants::BINDING_HTTP_REDIRECT
            );
        }

        if ($this->idpMetadataFile !== '') {
            $path = Path::isAbsolute($this->idpMetadataFile)
                ? $this->idpMetadataFile
                : $this->projectDir . '/' . ltrim($this->idpMetadataFile, '/');

            return IdPMetadataParser::parseFileXML(
                $path,
                null,
                null,
                Constants::BINDING_HTTP_REDIRECT,
                Constants::BINDING_HTTP_REDIRECT
            );
        }

        if ($this->idpMetadataUrl !== '') {
            return IdPMetadataParser::parseRemoteXML(
                $this->idpMetadataUrl,
                null,
                null,
                Constants::BINDING_HTTP_REDIRECT,
                Constants::BINDING_HTTP_REDIRECT
            );
        }

        return [];
    }
}
