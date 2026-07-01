<?php

namespace Vendor\LicenseGuard\Tests\Unit;

use Vendor\LicenseGuard\Support\EnvFileWriter;
use Vendor\LicenseGuard\Tests\TestCase;

class EnvFileWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'env-writer-test-');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_appends_keys_that_are_not_present(): void
    {
        file_put_contents($this->path, "APP_NAME=Test\nAPP_ENV=local\n");

        $result = (new EnvFileWriter())->write($this->path, [
            'LICENSE_KEY' => 'ABCD-1234',
            'LICENSE_SECRET' => 'topsecret',
        ]);

        $this->assertSame(['LICENSE_KEY', 'LICENSE_SECRET'], $result['added']);
        $this->assertSame([], $result['updated']);

        $contents = file_get_contents($this->path);
        $this->assertStringContainsString("APP_NAME=Test\n", $contents);
        $this->assertStringContainsString("APP_ENV=local\n", $contents);
        $this->assertStringContainsString("LICENSE_KEY=ABCD-1234\n", $contents);
        $this->assertStringContainsString('LICENSE_SECRET=topsecret', $contents);
    }

    public function test_replaces_existing_keys_in_place_and_leaves_everything_else_untouched(): void
    {
        file_put_contents($this->path, implode("\n", [
            '# App config',
            'APP_NAME=Test',
            'LICENSE_KEY=OLD-KEY',
            '',
            '# License config',
            'LICENSE_SECRET=old-secret',
            'APP_DEBUG=true',
        ]));

        $result = (new EnvFileWriter())->write($this->path, [
            'LICENSE_KEY' => 'NEW-KEY',
            'LICENSE_SECRET' => 'new-secret',
        ]);

        $this->assertSame([], $result['added']);
        $this->assertSame(['LICENSE_KEY', 'LICENSE_SECRET'], $result['updated']);

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);

        $this->assertSame([
            '# App config',
            'APP_NAME=Test',
            'LICENSE_KEY=NEW-KEY',
            '',
            '# License config',
            'LICENSE_SECRET=new-secret',
            'APP_DEBUG=true',
        ], $lines);
    }

    public function test_quotes_values_containing_spaces_or_hash(): void
    {
        file_put_contents($this->path, '');

        (new EnvFileWriter())->write($this->path, [
            'LICENSE_SERVER_URL' => 'https://license.example.com',
            'SOME_LABEL' => 'has a space',
            'SOME_COMMENT_LIKE' => 'value#withhash',
        ]);

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);

        $this->assertContains('LICENSE_SERVER_URL=https://license.example.com', $lines);
        $this->assertContains('SOME_LABEL="has a space"', $lines);
        $this->assertContains('SOME_COMMENT_LIKE="value#withhash"', $lines);
    }

    public function test_creates_file_when_it_does_not_exist(): void
    {
        unlink($this->path);

        $result = (new EnvFileWriter())->write($this->path, ['LICENSE_KEY' => 'ABCD-1234']);

        $this->assertSame(['LICENSE_KEY'], $result['added']);
        $this->assertSame("LICENSE_KEY=ABCD-1234\n", file_get_contents($this->path));
    }
}
