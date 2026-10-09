<?php

/**
 * Validates data against a JSON Schema (draft 2020-12), as far as the schemas this repository
 * keeps need it: type, const, enum, required, properties, additionalProperties, propertyNames,
 * pattern, minLength, maxLength, minimum, maximum, minItems, items, uniqueItems, oneOf, allOf,
 * if/then, local $ref (#/$defs/...) and the date and date-time formats. A keyword it does not
 * know is ignored, as the specification says of unknown keywords. Written here rather than
 * taken from a library: the import contract (#56) needs this much, and nothing more is worth a
 * dependency.
 *
 * Data is what json_decode($json, true) returns, so a JSON object and a JSON array are both PHP
 * arrays; an empty one counts as an object where the schema expects an object.
 */
class Jkl_JsonSchema
{
    private $root;

    public function __construct(array $schema)
    {
        $this->root = $schema;
    }

    public static function fromFile($path)
    {
        $schema = json_decode(file_get_contents($path), true);
        if (!is_array($schema)) {
            throw new InvalidArgumentException(sprintf('Not a JSON schema: %s', $path));
        }
        return new self($schema);
    }

    /**
     * @return string[] what is wrong, each as "path: message"; empty when the data is valid
     */
    public function validate($data)
    {
        return $this->check($data, $this->root, '$');
    }

    private function check($data, array $schema, $path)
    {
        if (isset($schema['$ref'])) {
            return $this->check($data, $this->resolve($schema['$ref']), $path);
        }
        $errors = array();

        if (isset($schema['type']) && !$this->hasType($data, (array) $schema['type'])) {
            return array("$path: expected " . implode(' or ', (array) $schema['type']) . ', got ' . $this->typeOf($data));
        }
        if (array_key_exists('const', $schema) && $data !== $schema['const']) {
            $errors[] = "$path: expected " . json_encode($schema['const']);
        }
        if (isset($schema['enum']) && !in_array($data, $schema['enum'], true)) {
            $errors[] = "$path: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' is not one of ' . json_encode($schema['enum'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($data)) {
            $length = mb_strlen($data, 'UTF-8');
            if (isset($schema['minLength']) && $length < $schema['minLength']) {
                $errors[] = "$path: shorter than {$schema['minLength']}";
            }
            if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
                $errors[] = "$path: longer than {$schema['maxLength']}";
            }
            if (isset($schema['pattern']) && !preg_match('/' . str_replace('/', '\/', $schema['pattern']) . '/u', $data)) {
                $errors[] = "$path: does not match {$schema['pattern']}";
            }
            if (isset($schema['format']) && !$this->hasFormat($data, $schema['format'])) {
                $errors[] = "$path: not a {$schema['format']}";
            }
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "$path: below {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "$path: above {$schema['maximum']}";
            }
        }

        if (is_array($data) && $this->isList($data) && (!empty($data) || !$this->expectsObject($schema))) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "$path: fewer than {$schema['minItems']} items";
            }
            if (!empty($schema['uniqueItems']) && count($data) !== count(array_unique(array_map('serialize', $data)))) {
                $errors[] = "$path: items are not unique";
            }
            if (isset($schema['items'])) {
                foreach ($data as $i => $item) {
                    $errors = array_merge($errors, $this->check($item, $schema['items'], "{$path}[{$i}]"));
                }
            }
        } elseif (is_array($data)) {
            foreach (isset($schema['required']) ? $schema['required'] : array() as $key) {
                if (!array_key_exists($key, $data)) {
                    $errors[] = "$path: {$key} is missing";
                }
            }
            foreach ($data as $key => $value) {
                $key = (string) $key;
                if (isset($schema['propertyNames'])) {
                    $errors = array_merge($errors, $this->check($key, $schema['propertyNames'], "$path.$key (name)"));
                }
                if (isset($schema['properties'][$key])) {
                    $errors = array_merge($errors, $this->check($value, $schema['properties'][$key], "$path.$key"));
                } elseif (isset($schema['additionalProperties'])) {
                    if (false === $schema['additionalProperties']) {
                        $errors[] = "$path: {$key} is not allowed";
                    } elseif (is_array($schema['additionalProperties'])) {
                        $errors = array_merge($errors, $this->check($value, $schema['additionalProperties'], "$path.$key"));
                    }
                }
            }
        }

        foreach (isset($schema['allOf']) ? $schema['allOf'] : array() as $sub) {
            $errors = array_merge($errors, $this->check($data, $sub, $path));
        }
        if (isset($schema['oneOf'])) {
            $matching = 0;
            $closest = null;
            $closestScore = null;
            foreach ($schema['oneOf'] as $sub) {
                $subErrors = $this->check($data, $sub, $path);
                if (empty($subErrors)) {
                    $matching++;
                    continue;
                }
                // An alternative of another type does not apply at all; of those that do, the
                // one with the fewest problems is the one the data was meant to be.
                $wrongType = 0 === strpos($subErrors[0], "$path: expected ");
                $score = array($wrongType ? 1 : 0, count($subErrors));
                if (null === $closestScore || $score < $closestScore) {
                    $closest = $subErrors;
                    $closestScore = $score;
                }
            }
            if (0 === $matching) {
                // The alternative it came closest to says best what is wrong.
                $errors = array_merge($errors, $closest);
            } elseif ($matching > 1) {
                $errors[] = "$path: matches more than one alternative";
            }
        }
        if (isset($schema['if'], $schema['then']) && empty($this->check($data, $schema['if'], $path))) {
            $errors = array_merge($errors, $this->check($data, $schema['then'], $path));
        }
        return $errors;
    }

    private function resolve($ref)
    {
        if (0 !== strpos($ref, '#/')) {
            throw new InvalidArgumentException("Only local references are supported: $ref");
        }
        $node = $this->root;
        foreach (explode('/', substr($ref, 2)) as $part) {
            if (!isset($node[$part])) {
                throw new InvalidArgumentException("No such definition: $ref");
            }
            $node = $node[$part];
        }
        return $node;
    }

    private function hasType($data, array $types)
    {
        foreach ($types as $type) {
            switch ($type) {
                case 'null':
                    if (null === $data) {
                        return true;
                    }
                    break;
                case 'boolean':
                    if (is_bool($data)) {
                        return true;
                    }
                    break;
                case 'integer':
                    if (is_int($data)) {
                        return true;
                    }
                    break;
                case 'number':
                    if (is_int($data) || is_float($data)) {
                        return true;
                    }
                    break;
                case 'string':
                    if (is_string($data)) {
                        return true;
                    }
                    break;
                case 'array':
                    if (is_array($data) && $this->isList($data)) {
                        return true;
                    }
                    break;
                case 'object':
                    if (is_array($data) && (empty($data) || !$this->isList($data))) {
                        return true;
                    }
                    break;
            }
        }
        return false;
    }

    private function typeOf($data)
    {
        if (null === $data) {
            return 'null';
        }
        if (is_bool($data)) {
            return 'boolean';
        }
        if (is_int($data)) {
            return 'integer';
        }
        if (is_float($data)) {
            return 'number';
        }
        if (is_string($data)) {
            return 'string';
        }
        return $this->isList($data) && !empty($data) ? 'array' : 'object';
    }

    private function expectsObject(array $schema)
    {
        return isset($schema['type']) && in_array('object', (array) $schema['type'], true)
            || isset($schema['properties']) || isset($schema['required']);
    }

    private function isList(array $data)
    {
        return array_keys($data) === range(0, count($data) - 1) || empty($data);
    }

    private function hasFormat($value, $format)
    {
        switch ($format) {
            case 'date':
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                return false !== $date && $date->format('Y-m-d') === $value;
            case 'date-time':
                return 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value)
                    && false !== date_create($value);
            default:
                return true;
        }
    }
}
