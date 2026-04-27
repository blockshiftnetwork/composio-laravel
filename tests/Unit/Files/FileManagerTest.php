<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Files;

use BlockshiftNetwork\Composio\Api\FilesApi;
use BlockshiftNetwork\Composio\Model\Error;
use BlockshiftNetwork\ComposioLaravel\Exceptions\ComposioException;
use BlockshiftNetwork\ComposioLaravel\Files\FileManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use stdClass;

class FileManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lists_files(): void
    {
        $expected = new stdClass;
        $api = Mockery::mock(FilesApi::class);
        $api->shouldReceive('getFilesList')
            ->once()
            ->with('github', 'GITHUB_UPLOAD', 25, 'cur')
            ->andReturn($expected);

        $this->assertSame(
            $expected,
            (new FileManager($api))->list('github', 'GITHUB_UPLOAD', 25, 'cur'),
        );
    }

    public function test_throws_when_list_fails(): void
    {
        $error = Mockery::mock(Error::class);
        $error->shouldReceive('getError')->andReturn('boom');

        $api = Mockery::mock(FilesApi::class);
        $api->shouldReceive('getFilesList')->once()->andReturn($error);

        $this->expectException(ComposioException::class);
        $this->expectExceptionMessage('Failed to list files: boom');

        (new FileManager($api))->list();
    }
}
