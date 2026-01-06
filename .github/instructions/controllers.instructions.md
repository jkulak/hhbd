---
applyTo: "app/application/controllers/**/*.php"
---

# Controller Instructions

## Zend Framework 1 Controller Patterns

Controllers extend `Zend_Controller_Action` and handle HTTP requests in the MVC pattern.

## Naming Conventions

- Class name: `AlbumController`, `ArtistController`, `UserController`
- File location: `application/controllers/AlbumController.php`
- Action methods: `indexAction()`, `viewAction()`, `listAction()`
- URL mapping: `/album/view/id/123` → `AlbumController::viewAction()`

## Standard Controller Structure

```php
class AlbumController extends Zend_Controller_Action
{
    public function init()
    {
        // Initialize controller (runs before every action)
        // Load config, set up view variables
    }
    
    public function indexAction()
    {
        // Handle /album/index
        // Typically redirects to list action or shows default page
    }
    
    public function viewAction()
    {
        // Handle /album/view/id/123
        $id = $this->getRequest()->getParam('id');
        
        $albumApi = Model_Album_Api::getInstance();
        $album = $albumApi->find($id, true);
        
        if (!$album) {
            throw new Zend_Controller_Action_Exception('Album not found', 404);
        }
        
        $this->view->album = $album;
        $this->view->title = $album->title;
    }
    
    public function listAction()
    {
        // Handle /album/list
        $albumApi = Model_Album_Api::getInstance();
        $albums = $albumApi->getList("SELECT * FROM albums ORDER BY added DESC LIMIT 50");
        
        $this->view->albums = $albums;
    }
}
```

## Key Patterns

### Getting Request Parameters

```php
// GET/POST parameter
$id = $this->getRequest()->getParam('id');
$page = $this->getRequest()->getParam('page', 1); // with default

// Check if POST
if ($this->getRequest()->isPost()) {
    $data = $this->getRequest()->getPost();
}
```

### Passing Data to Views

```php
// Assign variables to view
$this->view->album = $album;
$this->view->artist = $artist;
$this->view->pageTitle = 'Album Details';

// View accesses as: $this->album, $this->artist
```

### Redirects

```php
// Redirect to another action in same controller
$this->_helper->redirector('list');

// Redirect to different controller
$this->_helper->redirector('view', 'artist', null, ['id' => $artistId]);

// External redirect
$this->_redirect('/album-' . $slug . '-a' . $id . '.html');
```

### Error Handling

```php
// 404 Not Found
if (!$album) {
    throw new Zend_Controller_Action_Exception('Album not found', 404);
}

// Redirect on error
if (!$isValid) {
    $this->_helper->flashMessenger->addMessage('Error: Invalid data');
    $this->_helper->redirector('index');
}
```

### Forms and Validation

```php
public function addAction()
{
    if ($this->getRequest()->isPost()) {
        $data = $this->getRequest()->getPost();
        
        // Validate
        if (empty($data['title'])) {
            $this->view->error = 'Title is required';
            return;
        }
        
        // Process
        $albumApi = Model_Album_Api::getInstance();
        $newId = $albumApi->create($data);
        
        $this->_helper->redirector('view', null, null, ['id' => $newId]);
    }
    
    // Show form
    $this->view->artists = Model_Artist_Api::getInstance()->getList("SELECT * FROM artists ORDER BY name");
}
```

### Authentication Check

```php
public function init()
{
    $auth = Zend_Auth::getInstance();
    if (!$auth->hasIdentity()) {
        $this->_helper->redirector('login', 'user');
    }
    
    // Get logged-in user
    $this->view->loggedInUser = $auth->getIdentity();
}
```

### Admin-Only Actions

```php
public function deleteAction()
{
    $auth = Zend_Auth::getInstance();
    $user = $auth->getIdentity();
    
    if (!$user || !$user->usr_is_admin) {
        throw new Zend_Controller_Action_Exception('Access denied', 403);
    }
    
    // Admin functionality
}
```

## Layout and View Rendering

### Default Rendering
- Action `viewAction()` automatically renders `views/scripts/album/view.phtml`
- No need to explicitly call render() in most cases

### Custom View Script
```php
$this->render('custom-template'); // renders album/custom-template.phtml
```

### Disable Layout
```php
$this->_helper->layout()->disableLayout(); // for AJAX responses
```

### JSON Response
```php
$this->_helper->layout()->disableLayout();
$this->_helper->viewRenderer->setNoRender(true);
$this->getResponse()->setHeader('Content-Type', 'application/json');
echo json_encode(['success' => true, 'data' => $data]);
```

## URL Generation in Controllers

Use helper for SEO-friendly URLs:
```php
$url = '/album-' . Jkl_Tools_String::toAscii($album->title) . '-a' . $album->id . '.html';
$this->_redirect($url);
```

## Database Access Pattern

**Always use Model Api classes, never direct database access:**

```php
// CORRECT
$albumApi = Model_Album_Api::getInstance();
$album = $albumApi->find($id);

// WRONG - don't do this
$db = Zend_Registry::get('db');
$album = $db->fetchRow("SELECT * FROM albums WHERE id = ?", $id);
```

## Key Rules

1. **One action = one responsibility**: Each action should handle one specific request
2. **Use Model Api**: Never write SQL in controllers
3. **Validate input**: Always validate and sanitize request parameters
4. **Handle errors gracefully**: Use 404/403 exceptions, not die() or echo
5. **Pass objects to views**: Use Container objects, not raw arrays
6. **Follow naming conventions**: `viewAction()` not `view()`, `AlbumController` not `albumController`
7. **Don't echo in actions**: Pass data to view via `$this->view->*`
8. **Use redirector helper**: For redirects, not `header('Location: ...')`
