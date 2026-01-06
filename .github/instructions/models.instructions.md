---
applyTo: "app/application/models/**/*.php"
---

# Model Layer Instructions

## Two-Tier Model Architecture

All models in this application follow a strict **Api + Container** pattern:

### Model_*_Api (Data Access Layer)
- **Singleton pattern**: Use `private static $_instance` and `getInstance()` method
- **Extends**: `Jkl_Model_Api` (provides `$this->_db` access)
- **Purpose**: Database queries, caching, data retrieval
- **Methods**:
  - `getInstance()`: Returns singleton instance
  - `find($id, $full = false)`: Fetch single entity by ID
  - `getList($query)`: Return `Jkl_List` collection of Container objects
- **Never instantiate with `new`**: Always use `Model_*_Api::getInstance()`

### Model_*_Container (Data Transfer Object)
- **Purpose**: Entity data holder, business logic, formatting
- **Constructor**: Accepts raw database `$params` array and transforms to properties
- **Public properties**: Direct access (e.g., `$album->title`, `$album->id`)
- **Methods**: Getters, formatters, URL generators (e.g., `getUrl()`)
- **No database access**: Containers never query the database directly

## Naming Conventions

- Api class: `Model_Album_Api` → `models/Album/Api.php`
- Container class: `Model_Album_Container` → `models/Album/Container.php`
- Use underscores for namespace separation (PSR-0 style for ZF1)

## Example Pattern

```php
// Api class
class Model_Album_Api extends Jkl_Model_Api
{
    private static $_instance;
    
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    public function find($id, $full = false)
    {
        $query = "SELECT * FROM albums WHERE id = ?";
        $result = $this->_db->fetchRow($query, $id);
        return new Model_Album_Container($result, $full);
    }
    
    public function getList($query)
    {
        $result = $this->_db->fetchAll($query);
        $list = new Jkl_List();
        foreach ($result as $params) {
            $list->add(new Model_Album_Container($params));
        }
        return $list;
    }
}

// Container class
class Model_Album_Container
{
    public $id;
    public $title;
    public $artist;
    
    public function __construct($params, $full = false)
    {
        $this->id = $params['alb_id'];
        $this->title = $params['title'];
        
        // Lazy load related entities
        if (!empty($params['art_id'])) {
            $this->artist = Model_Artist_Api::getInstance()->find($params['art_id']);
        }
    }
    
    public function getUrl()
    {
        return '/album-' . Jkl_Tools_String::toAscii($this->title) . '-a' . $this->id . '.html';
    }
}
```

## Key Rules

1. **Never create Api with `new`**: Always use `::getInstance()`
2. **Containers are created by Api**: Use `new Model_*_Container($params)`
3. **Return Jkl_List for collections**: Not plain arrays
4. **Lazy load related entities**: Load in Container constructor when needed
5. **Use Jkl_Db for queries**: Available via `$this->_db` in Api classes
6. **ID field naming**: Database uses `alb_id`, `art_id` prefixes; normalize in Container to `$this->id`
