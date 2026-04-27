<?php

namespace BlockshiftNetwork\ComposioLaravel\Tests\Unit\Tools;

use BlockshiftNetwork\ComposioLaravel\Tools\CustomTool;
use BlockshiftNetwork\ComposioLaravel\Tools\CustomToolRegistry;
use PHPUnit\Framework\TestCase;

class CustomToolRegistryTest extends TestCase
{
    public function test_registers_and_retrieves_a_tool(): void
    {
        $registry = new CustomToolRegistry;
        $registry->register('GREET', 'Greets a user', [
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ], fn (array $args) => "Hello {$args['name']}");

        $this->assertTrue($registry->has('GREET'));
        $tool = $registry->get('GREET');

        $this->assertInstanceOf(CustomTool::class, $tool);
        $this->assertSame('GREET', $tool->slug);
        $this->assertSame('Greets a user', $tool->description);
        $this->assertSame('Hello Alice', $tool->execute(['name' => 'Alice']));
    }

    public function test_all_returns_registered_tools(): void
    {
        $registry = new CustomToolRegistry;
        $registry->register('A', 'a', [], fn () => '1');
        $registry->register('B', 'b', [], fn () => '2');

        $this->assertCount(2, $registry->all());
    }

    public function test_unregister_removes_a_tool(): void
    {
        $registry = new CustomToolRegistry;
        $registry->register('A', 'a', [], fn () => '1');
        $registry->unregister('A');

        $this->assertFalse($registry->has('A'));
        $this->assertNull($registry->get('A'));
    }

    public function test_array_results_are_json_encoded(): void
    {
        $registry = new CustomToolRegistry;
        $registry->register('SUM', 'sums', [], fn (array $args) => ['total' => $args['a'] + $args['b']]);

        $output = $registry->get('SUM')->execute(['a' => 2, 'b' => 3]);
        $this->assertSame('{"total":5}', $output);
    }
}
