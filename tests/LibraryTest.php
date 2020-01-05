<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Configurator');
    }

    public function testValidateDiscovery(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Discovery');
    }

    public function testValidateIO(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF IO');
    }

    public function testValidateDI(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Digital Input');
    }
    public function testValidateDO(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Digital Output');
    }
    public function testValidateEvents(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Events');
    }
    public function testValidateStream(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF Media Stream');
    }
    public function testValidatePTZ(): void
    {
        $this->validateModule(__DIR__ . '/../ONVIF PTZ');
    }
}