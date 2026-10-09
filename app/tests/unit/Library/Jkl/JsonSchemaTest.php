<?php

use PHPUnit\Framework\TestCase;

/**
 * The JSON Schema subset the import contract needs (#56).
 */
class Jkl_JsonSchemaTest extends TestCase
{
    private function errors(array $schema, $data): array
    {
        return (new Jkl_JsonSchema($schema))->validate($data);
    }

    public function testValidDataHasNoErrors(): void
    {
        $schema = array('type' => 'object', 'required' => array('a'), 'properties' => array('a' => array('type' => 'integer', 'minimum' => 1)));
        $this->assertSame(array(), $this->errors($schema, array('a' => 3)));
    }

    public function testEachProblemIsNamedWithItsPath(): void
    {
        $schema = array('type' => 'object', 'required' => array('a', 'b'), 'properties' => array(
            'a' => array('type' => 'integer', 'minimum' => 1),
            'c' => array('type' => 'array', 'items' => array('enum' => array('x', 'y'))),
        ));

        $this->assertSame(
            array('$: b is missing', '$.a: below 1', '$.c[1]: "z" is not one of ["x","y"]'),
            $this->errors($schema, array('a' => 0, 'c' => array('x', 'z')))
        );
    }

    public function testAnEmptyArrayIsAnObjectWhereAnObjectIsExpected(): void
    {
        $this->assertSame(array(), $this->errors(array('type' => 'object'), array()));
        $this->assertSame(array('$: x is missing'), $this->errors(array('type' => 'object', 'required' => array('x')), array()));
    }

    public function testNullableTypesAcceptNull(): void
    {
        $this->assertSame(array(), $this->errors(array('type' => array('string', 'null')), null));
        $this->assertSame(array('$: expected string or null, got integer'), $this->errors(array('type' => array('string', 'null')), 1));
    }

    public function testPatternsAndLengthsCountCharactersNotBytes(): void
    {
        $this->assertSame(array(), $this->errors(array('type' => 'string', 'maxLength' => 6, 'pattern' => '^Ż'), 'Żabson'));
        $this->assertSame(array('$: does not match ^name:.+$'), $this->errors(array('pattern' => '^name:.+$'), 'Żabson'));
    }

    public function testOneOfReportsTheAlternativeItCameClosestTo(): void
    {
        $schema = array('oneOf' => array(
            array('type' => 'null'),
            array('type' => 'object', 'required' => array('ref'), 'properties' => array('ref' => array('type' => 'string'))),
        ));

        $this->assertSame(array(), $this->errors($schema, null));
        $this->assertSame(array(), $this->errors($schema, array('ref' => 'label:x')));
        $this->assertSame(array('$.ref: expected string, got integer'), $this->errors($schema, array('ref' => 5)));
    }

    public function testReferencesResolveToDefinitions(): void
    {
        $schema = array('$defs' => array('id' => array('type' => 'integer')), 'type' => 'array', 'items' => array('$ref' => '#/$defs/id'));
        $this->assertSame(array('$[1]: expected integer, got string'), $this->errors($schema, array(1, 'two')));
    }

    public function testIfThenAppliesOnlyWhenTheConditionHolds(): void
    {
        $schema = array('if' => array('properties' => array('role' => array('const' => 'cover'))), 'then' => array('required' => array('variants')));
        $this->assertSame(array('$: variants is missing'), $this->errors($schema, array('role' => 'cover')));
        $this->assertSame(array(), $this->errors($schema, array('role' => 'photo')));
    }

    /**
     * @dataProvider formatsProvider
     */
    public function testDatesAndTimesAreChecked(string $format, string $value, bool $valid): void
    {
        $this->assertSame($valid, array() === $this->errors(array('format' => $format), $value));
    }

    public function formatsProvider(): array
    {
        return array(
            'a date' => array('date', '2016-11-03', true),
            'a day that does not exist' => array('date', '2016-02-30', false),
            'a date with zero parts' => array('date', '2016-11-00', false),
            'a time with a zone' => array('date-time', '2026-10-09T12:00:00Z', true),
            'a time without a zone' => array('date-time', '2026-10-09T12:00:00', false),
        );
    }

    public function testPropertyNamesAreChecked(): void
    {
        $schema = array('type' => 'object', 'propertyNames' => array('enum' => array('cd', 'lp')));
        $this->assertSame(array('$.dvd (name): "dvd" is not one of ["cd","lp"]'), $this->errors($schema, array('cd' => 'X', 'dvd' => 'Y')));
    }
}
