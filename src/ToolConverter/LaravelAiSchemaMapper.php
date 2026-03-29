<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class LaravelAiSchemaMapper
{
    public function mapProperties(JsonSchema $schema, array $jsonSchema): array
    {
        $properties = $jsonSchema['properties'] ?? [];
        $required = $jsonSchema['required'] ?? [];
        $mapped = [];

        foreach ($properties as $name => $propertySchema) {
            $isRequired = in_array($name, $required, true);
            $mapped[$name] = $this->mapProperty($schema, $propertySchema, $isRequired);
        }

        return $mapped;
    }

    private function mapProperty(JsonSchema $schema, array $propertySchema, bool $required): mixed
    {
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

    private function mapObjectType(JsonSchema $schema, array $propertySchema): mixed
    {
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
}
