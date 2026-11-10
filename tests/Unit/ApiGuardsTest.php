<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/api-guards.php';

class ApiGuardsTest extends TestCase
{
    /**
     * Only the field names of an update body are listed, never the values.
     */
    public function testDescribeFieldNamesOnly(): void
    {
        $fields = describeUpdatedFields(['firstName' => 'Guilherme', 'email' => 'secret@example.com']);

        $this->assertSame('firstName, email', $fields);
        $this->assertStringNotContainsString('secret', $fields);
    }

    /**
     * A body that is not an object is described as empty.
     */
    public function testDescribeNonArrayBodies(): void
    {
        $this->assertSame('', describeUpdatedFields(null));
        $this->assertSame('', describeUpdatedFields('text'));
    }

    /**
     * An existing configuration file lets the request continue.
     */
    public function testExistingConfigurationFilePasses(): void
    {
        ensureConfigurationFileExists(__FILE__, 'test');

        $this->assertTrue(true);
    }

    /**
     * An authenticated request continues without output.
     */
    public function testAuthenticatedRequestPasses(): void
    {
        $this->expectOutputString('');

        ensureAuthenticated(true, 'test');
    }
}
