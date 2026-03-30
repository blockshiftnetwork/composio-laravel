<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\ComposioLaravel\ToolConverter\SchemaMapper;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool;

class SchemaMapperTest extends TestCase
{
    private SchemaMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SchemaMapper;
    }

    public function test_maps_string_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The name'],
            ],
            'required' => ['name'],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
        $this->assertContains('name', $result->requiredParameters());
    }

    public function test_maps_number_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'count' => ['type' => 'number', 'description' => 'The count'],
            ],
            'required' => [],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
        $this->assertNotContains('count', $result->requiredParameters());
    }

    public function test_maps_integer_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Page number'],
            ],
            'required' => ['page'],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_maps_boolean_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'active' => ['type' => 'boolean', 'description' => 'Is active'],
            ],
            'required' => [],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_maps_enum_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'description' => 'The status',
                    'enum' => ['open', 'closed', 'pending'],
                ],
            ],
            'required' => ['status'],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_maps_array_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'labels' => [
                    'type' => 'array',
                    'description' => 'List of labels',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_maps_object_parameter(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'config' => [
                    'type' => 'object',
                    'description' => 'Configuration',
                    'properties' => [
                        'key' => ['type' => 'string', 'description' => 'Config key'],
                        'value' => ['type' => 'string', 'description' => 'Config value'],
                    ],
                    'required' => ['key'],
                ],
            ],
            'required' => ['config'],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_maps_multiple_parameters(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'owner' => ['type' => 'string', 'description' => 'Repository owner'],
                'repo' => ['type' => 'string', 'description' => 'Repository name'],
                'page' => ['type' => 'integer', 'description' => 'Page number'],
            ],
            'required' => ['owner', 'repo'],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(3, $params);
        $this->assertContains('owner', $result->requiredParameters());
        $this->assertContains('repo', $result->requiredParameters());
        $this->assertNotContains('page', $result->requiredParameters());
    }

    public function test_handles_unknown_type_as_string(): void
    {
        $tool = new Tool;
        $schema = [
            'properties' => [
                'data' => ['type' => 'unknown_type', 'description' => 'Some data'],
            ],
            'required' => [],
        ];

        $result = $this->mapper->applySchema($tool, $schema);

        $params = $result->parameters();
        $this->assertCount(1, $params);
    }

    public function test_handles_empty_schema(): void
    {
        $tool = new Tool;
        $result = $this->mapper->applySchema($tool, []);

        $params = $result->parameters();
        $this->assertCount(0, $params);
    }
}
