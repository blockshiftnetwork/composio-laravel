<?php

namespace BlockshiftNetwork\ComposioLaravel\ToolConverter;

use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool;

class SchemaMapper
{
    public function applySchema(Tool $tool, array $jsonSchema): Tool
    {
        $properties = $jsonSchema['properties'] ?? [];
        $required = $jsonSchema['required'] ?? [];

        foreach ($properties as $name => $propertySchema) {
            $isRequired = in_array($name, $required, true);
            $tool = $this->applyProperty($tool, $name, $propertySchema, $isRequired);
        }

        return $tool;
    }

    private function applyProperty(Tool $tool, string $name, array $schema, bool $required): Tool
    {
        $type = $schema['type'] ?? 'string';
        $description = $schema['description'] ?? '';

        if ($type === 'string' && isset($schema['enum'])) {
            return $tool->withEnumParameter($name, $description, $schema['enum'], $required);
        }

        return match ($type) {
            'string' => $tool->withStringParameter($name, $description, $required),
            'number', 'integer' => $tool->withNumberParameter($name, $description, $required),
            'boolean' => $tool->withBooleanParameter($name, $description, $required),
            'array' => $tool->withArrayParameter(
                $name,
                $description,
                $this->mapItemsSchema($schema['items'] ?? []),
                $required,
            ),
            'object' => $tool->withObjectParameter(
                $name,
                $description,
                $this->mapObjectProperties($schema),
                $schema['required'] ?? [],
                true,
                $required,
            ),
            default => $tool->withStringParameter($name, $description, $required),
        };
    }

    private function mapItemsSchema(array $items): StringSchema|NumberSchema|BooleanSchema
    {
        $type = $items['type'] ?? 'string';
        $description = $items['description'] ?? 'item';

        return match ($type) {
            'number', 'integer' => new NumberSchema('item', $description),
            'boolean' => new BooleanSchema('item', $description),
            default => new StringSchema('item', $description),
        };
    }

    private function mapObjectProperties(array $schema): array
    {
        $properties = [];
        $nestedProps = $schema['properties'] ?? [];

        foreach ($nestedProps as $name => $propSchema) {
            $type = $propSchema['type'] ?? 'string';
            $description = $propSchema['description'] ?? '';

            $properties[$name] = match ($type) {
                'number', 'integer' => new NumberSchema($name, $description),
                'boolean' => new BooleanSchema($name, $description),
                default => new StringSchema($name, $description),
            };
        }

        return $properties;
    }
}
