<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit;

use BlockshiftNetwork\ComposioLaravel\ToolConverter\LaravelAiSchemaMapper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\ObjectType;
use Mockery;
use PHPUnit\Framework\TestCase;

class LaravelAiSchemaMapperTest extends TestCase
{
    private LaravelAiSchemaMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(JsonSchema::class)) {
            $this->markTestSkipped('Laravel AI is not installed');
        }

        $this->mapper = new LaravelAiSchemaMapper;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_maps_string_property(): void
    {
        $schema = $this->createMockJsonSchema();
        $jsonSchema = [
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'The name'],
            ],
            'required' => ['name'],
        ];

        $result = $this->mapper->mapProperties($schema, $jsonSchema);

        $this->assertArrayHasKey('name', $result);
    }

    public function test_maps_integer_property(): void
    {
        $schema = $this->createMockJsonSchema();
        $jsonSchema = [
            'properties' => [
                'count' => ['type' => 'integer', 'description' => 'The count'],
            ],
            'required' => [],
        ];

        $result = $this->mapper->mapProperties($schema, $jsonSchema);

        $this->assertArrayHasKey('count', $result);
    }

    public function test_maps_number_property(): void
    {
        $schema = $this->createMockJsonSchema();
        $jsonSchema = [
            'properties' => [
                'price' => ['type' => 'number', 'description' => 'The price'],
            ],
            'required' => ['price'],
        ];

        $result = $this->mapper->mapProperties($schema, $jsonSchema);

        $this->assertArrayHasKey('price', $result);
    }

    public function test_maps_boolean_property(): void
    {
        $schema = $this->createMockJsonSchema();
        $jsonSchema = [
            'properties' => [
                'active' => ['type' => 'boolean', 'description' => 'Is active'],
            ],
            'required' => [],
        ];

        $result = $this->mapper->mapProperties($schema, $jsonSchema);

        $this->assertArrayHasKey('active', $result);
    }

    public function test_maps_multiple_properties(): void
    {
        $schema = $this->createMockJsonSchema();
        $jsonSchema = [
            'properties' => [
                'owner' => ['type' => 'string', 'description' => 'Owner'],
                'repo' => ['type' => 'string', 'description' => 'Repo'],
                'page' => ['type' => 'integer', 'description' => 'Page'],
            ],
            'required' => ['owner', 'repo'],
        ];

        $result = $this->mapper->mapProperties($schema, $jsonSchema);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('owner', $result);
        $this->assertArrayHasKey('repo', $result);
        $this->assertArrayHasKey('page', $result);
    }

    public function test_handles_empty_schema(): void
    {
        $schema = $this->createMockJsonSchema();
        $result = $this->mapper->mapProperties($schema, []);

        $this->assertEmpty($result);
    }

    private function createMockJsonSchema(): JsonSchema
    {
        $schema = Mockery::mock(JsonSchema::class);

        $stringType = Mockery::mock(StringType::class);
        $stringType->shouldReceive('description')->andReturnSelf();
        $stringType->shouldReceive('required')->andReturnSelf();
        $stringType->shouldReceive('enum')->andReturnSelf();

        $integerType = Mockery::mock(IntegerType::class);
        $integerType->shouldReceive('description')->andReturnSelf();
        $integerType->shouldReceive('required')->andReturnSelf();

        $numberType = Mockery::mock(NumberType::class);
        $numberType->shouldReceive('description')->andReturnSelf();
        $numberType->shouldReceive('required')->andReturnSelf();

        $booleanType = Mockery::mock(BooleanType::class);
        $booleanType->shouldReceive('description')->andReturnSelf();
        $booleanType->shouldReceive('required')->andReturnSelf();

        $arrayType = Mockery::mock(ArrayType::class);
        $arrayType->shouldReceive('description')->andReturnSelf();
        $arrayType->shouldReceive('required')->andReturnSelf();

        $objectType = Mockery::mock(ObjectType::class);
        $objectType->shouldReceive('description')->andReturnSelf();
        $objectType->shouldReceive('required')->andReturnSelf();

        $schema->shouldReceive('string')->andReturn($stringType);
        $schema->shouldReceive('integer')->andReturn($integerType);
        $schema->shouldReceive('number')->andReturn($numberType);
        $schema->shouldReceive('boolean')->andReturn($booleanType);
        $schema->shouldReceive('array')->andReturn($arrayType);
        $schema->shouldReceive('object')->andReturn($objectType);

        return $schema;
    }
}
