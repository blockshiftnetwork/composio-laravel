<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class LaravelAiSchemaMapper
{
    public function mapProperties(JsonSchema $schema, array|object $jsonSchema): array
    {
        $jsonSchema = $this->normalizeSchema($jsonSchema);
        $properties = $jsonSchema['properties'] ?? [];
        $required = $jsonSchema['required'] ?? [];
        $mapped = [];

        foreach ($properties as $name => $propertySchema) {
            $isRequired = in_array($name, $required, true);
            $mapped[$name] = $this->mapProperty($schema, $propertySchema, $isRequired);
        }

        return $mapped;
    }

    private function mapProperty(JsonSchema $schema, mixed $propertySchema, bool $required): mixed
    {
        $propertySchema = $this->normalizeSchema($propertySchema);
        $type = $propertySchema['type'] ?? 'string';
        $description = $propertySchema['description'] ?? '';

        $typeInstance = match ($type) {
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $this->mapObjectType($schema, $propertySchema),
            default => $schema->string(),
        };

        if ($description !== '') {
            $typeInstance = $typeInstance->description($description);
        }

        if (isset($propertySchema['enum'])) {
            $typeInstance = $typeInstance->enum($propertySchema['enum']);
        }

        if ($required) {
            $typeInstance = $typeInstance->required();
        }

        return $typeInstance;
    }

    private function mapObjectType(JsonSchema $schema, mixed $propertySchema): mixed
    {
        $propertySchema = $this->normalizeSchema($propertySchema);
        $nestedProperties = $propertySchema['properties'] ?? [];
        $nestedRequired = $propertySchema['required'] ?? [];

        if (empty($nestedProperties)) {
            return $schema->object();
        }

        $mapped = [];
        foreach ($nestedProperties as $name => $propSchema) {
            $isRequired = in_array($name, $nestedRequired, true);
            $mapped[$name] = $this->mapProperty($schema, $propSchema, $isRequired);
        }

        return $schema->object($mapped);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSchema(mixed $schema): array
    {
        if (is_array($schema)) {
            return $schema;
        }

        if ($schema instanceof \stdClass) {
            return json_decode(json_encode($schema), true) ?: [];
        }

        return [];
    }
}
