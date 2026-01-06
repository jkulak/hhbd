---
applyTo: "app/library/Jkl/**/*.php"
---

# Jkl Library Instructions

## Custom Library Overview

The `Jkl_*` library contains custom utility classes following Zend Framework 1 naming and autoloading conventions (PSR-0).

## Naming and File Structure

- Namespace separator: Underscore (`_`)
- Class name: `Jkl_Tools_String` maps to `library/Jkl/Tools/String.php`
- All library classes use the `Jkl_` prefix

## Key Library Classes

### Jkl_Model_Api
Base class for all `Model_*_Api` classes:
```php
abstract class Jkl_Model_Api
{
    protected $_db;  // Database adapter
    
    public function __construct()
    {
        $this->_db = Zend_Registry::get('db');
    }
}
```

Extend this for data access layers:
```php
class Model_Album_Api extends Jkl_Model_Api
{
    // Has access to $this->_db
}
```

### Jkl_Db
Database utility methods:
```php
// Cached query execution
$result = Jkl_Db::fetchAll($query, $params, $cacheKey, $cacheDuration);

// Non-cached queries
$result = Jkl_Db::fetchRow($query, $params);
```

### Jkl_List
Collection class used for entity lists:
```php
$albums = new Jkl_List();
$albums->add($album);
$albums->count();
foreach ($albums as $album) {
    // iterate
}
```

### Jkl_Tools_String
String manipulation utilities for Polish language:
```php
// Convert Polish characters to ASCII
$ascii = Jkl_Tools_String::toAscii('ąćęłńóśźż');
// Returns: 'acelnoszz'

// Create URL-friendly slugs
$slug = Jkl_Tools_String::slugify('Album Title!');
// Returns: 'album-title'

// Truncate with ellipsis
$short = Jkl_Tools_String::truncate($text, 100);
```

### Jkl_Tools_Date
Date formatting utilities:
```php
// Normalize date format
$normalized = Jkl_Tools_Date::getNormalDate('2010-05-15');
// Returns: '15.05.2010'

// Get relative time
$relative = Jkl_Tools_Date::getRelativeTime($timestamp);
// Returns: '2 days ago', '3 hours ago', etc.
```

### Jkl_Tools_Url
URL manipulation utilities:
```php
// Build SEO-friendly URLs
$url = Jkl_Tools_Url::buildAlbumUrl($albumId, $albumTitle);
// Returns: '/album-title-a123.html'

// Extract ID from URL
$id = Jkl_Tools_Url::extractIdFromUrl($url);
```

### Jkl_Og
Open Graph meta tag generator for social media:
```php
$og = new Jkl_Og();
$og->setTitle('Album Title');
$og->setDescription('Album description');
$og->setImage('https://example.com/image.jpg');
$og->setUrl('https://example.com/album');

$metaTags = $og->render();
```

## Coding Conventions

### Class Structure
```php
class Jkl_Tools_String
{
    /**
     * Convert Polish characters to ASCII
     *
     * @param string $text Input text with Polish characters
     * @return string ASCII-only text
     */
    public static function toAscii($text)
    {
        $polishChars = ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż'];
        $asciiChars = ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'];
        
        return str_replace($polishChars, $asciiChars, $text);
    }
}
```

### Static Methods
Most utility classes use static methods for convenience:
```php
// CORRECT
$result = Jkl_Tools_String::toAscii($text);

// NOT
$tools = new Jkl_Tools_String();
$result = $tools->toAscii($text);
```

### Non-Static Classes
Some classes are instantiated (e.g., `Jkl_List`, `Jkl_Og`):
```php
$list = new Jkl_List();
$og = new Jkl_Og();
```

## Testing Library Classes

All library classes should have comprehensive unit tests:
```php
class Jkl_Tools_StringTest extends PHPUnit\Framework\TestCase
{
    public function testToAsciiConvertsPolishCharacters(): void
    {
        $input = 'ąćęłńóśźż';
        $expected = 'acelnoszz';
        
        $result = Jkl_Tools_String::toAscii($input);
        
        $this->assertEquals($expected, $result);
    }
}
```

## Adding New Library Classes

When adding new utility classes:

1. **Follow naming convention**: `Jkl_Category_ClassName`
2. **Create matching file path**: `library/Jkl/Category/ClassName.php`
3. **Use static methods** for stateless utilities
4. **Add comprehensive tests**: `tests/unit/Library/Jkl/Category/ClassNameTest.php`
5. **Document with PHPDoc**: Describe purpose, parameters, return values
6. **Handle Polish strings**: Use proper encoding (UTF-8)

## Key Rules

1. **Follow PSR-0 naming**: Underscores map to directory separators
2. **Prefix with Jkl_**: All custom library classes
3. **Static for utilities**: Use static methods for stateless operations
4. **No database in Tools**: Only `Jkl_Db` and `Jkl_Model_Api` access database
5. **UTF-8 encoding**: Always handle Polish characters correctly
6. **Comprehensive tests**: All library classes must have unit tests
7. **Prefer Jkl over ad-hoc**: Use existing utilities instead of creating new inline functions
