<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
  // Override config with environment variables (12-factor app)
  protected function _initEnvConfig()
  {
    $options = $this->getOptions();

    // Database configuration
    if (getenv('DB_HOST')) {
      $options['resources']['db']['params']['host'] = getenv('DB_HOST');
    }
    if (getenv('DB_USER')) {
      $options['resources']['db']['params']['username'] = getenv('DB_USER');
    }
    if (getenv('DB_PASSWORD')) {
      $options['resources']['db']['params']['password'] = getenv('DB_PASSWORD');
    }
    if (getenv('DB_NAME')) {
      $options['resources']['db']['params']['dbname'] = getenv('DB_NAME');
    }

    // Mail configuration
    if (getenv('MAIL_HOST')) {
      $options['resources']['mail']['transport']['host'] = getenv('MAIL_HOST');
    }
    if (getenv('MAIL_USERNAME')) {
      $options['resources']['mail']['transport']['username'] = getenv('MAIL_USERNAME');
    }
    if (getenv('MAIL_PASSWORD')) {
      $options['resources']['mail']['transport']['password'] = getenv('MAIL_PASSWORD');
    }
    if (getenv('MAIL_FROM_EMAIL')) {
      $options['resources']['mail']['defaultFrom']['email'] = getenv('MAIL_FROM_EMAIL');
    }
    if (getenv('MAIL_FROM_NAME')) {
      $options['resources']['mail']['defaultFrom']['name'] = getenv('MAIL_FROM_NAME');
    }

    // Cache configuration
    if (getenv('CACHE_ENABLED') !== false) {
      $options['app']['cache']['front']['caching'] = filter_var(getenv('CACHE_ENABLED'), FILTER_VALIDATE_BOOLEAN);
    }
    if (getenv('CACHE_HOST')) {
      $options['app']['cache']['backend']['host'] = getenv('CACHE_HOST');
    }
    if (getenv('CACHE_PORT')) {
      $options['app']['cache']['backend']['port'] = (int) getenv('CACHE_PORT');
    }

    // Feature flags
    if (getenv('SHOW_ADS') !== false) {
      $options['app']['showAds'] = filter_var(getenv('SHOW_ADS'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    if (getenv('SHOW_SHARE_IT') !== false) {
      $options['app']['showShareIt'] = filter_var(getenv('SHOW_SHARE_IT'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    if (getenv('DEBUG_FIREPHP') !== false) {
      $options['app']['debug']['firePhpEnable'] = filter_var(getenv('DEBUG_FIREPHP'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    $this->setOptions($options);
  }

  // initiates autoloader for modules
  protected function _initAutoload()
  {
     $moduleLoader = new Zend_Application_Module_Autoloader(array(
       'namespace' => '',
       'basePath' => APPLICATION_PATH)
       );
    return $moduleLoader;
  }
  
  protected function _initApplication()
  {
    Zend_Registry::set('Logger', $this->bootstrap('log')->getResource('log'));
    
    // Load configuration from file, put it in the registry
    $appConfig = $this->getOption('app');
    Zend_Registry::set('Config_App', $appConfig);

    // Read Resources section and put it in registry
    $resourcesConfig = $this->getOption('resources');
    Zend_Registry::set('Config_Resources', $resourcesConfig);
    
    // Read Resources section and put it in registry
    $resourcesConfig = $this->getOption('resources');
    Zend_Registry::set('Memcached', Jkl_Cache::getInstance());

    // Start routing
    $frontController = Zend_Controller_Front::getInstance();
    $router = $frontController->getRouter();
    
    // In case I want to turn on translation
    // Zend_Controller_Router_Route::setDefaultTranslator($translator);
    $routes = new Zend_Config_Xml(APPLICATION_PATH . '/configs/routes.xml', APPLICATION_ENV);
    //$router->removeDefaultRoutes();
    $router->addConfig($routes, 'routes');
  }
       
  protected function _initView()
  {
    $this->bootstrap('layout');
    $layout = $this->getResource('layout');
    $view = $layout->getView();

    $view->doctype('HTML5');
    // $view->headMeta()->appendHttpEquiv('Content-Type', 'text/html;charset=utf-8');
    $view->headMeta()->setCharset('utf-8');    
    $view->headMeta()->setName('robots', 'index,follow');
    $view->headMeta()->setName('author', 'Jakub Kułak, www.webascrazy.net');
    $view->headTitle()->setSeparator(' - ');
    $view->headTitle('Hhbd.pl');
    
    $configApp = Zend_Registry::get('Config_App');
    $view->headIncludes = $configApp['includes'];
  }
}