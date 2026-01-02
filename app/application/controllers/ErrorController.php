<?php

class ErrorController extends Zend_Controller_Action
{

    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');
        
        if (!$errors) {
            $this->view->message = 'You have reached the error page';
            return;
        }
        
        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
        
                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->title = 'Błąd 404: Nieeee, nie mamy takiej strony (jeszcze)!';
                $this->view->message = 'Ale nie przejmuj się tym, to nie Twoja wina :) Sprawdź czy wpisałeś dobry adres, a najlepiej chodź na <a href="/">stronę główną</a> lub wpisz czego szukasz w naszej wyszukiwarce!';
                break;
                
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER:
                $this->getResponse()->setHttpResponseCode(500);
                switch($errors->exception->getCode()) {
                  
                  case 2002:
                    $this->_forward('exception-db-connection-failed');
                    break;
                    
                  case Jkl_Cache_Exception::EXCEPTION_MEMCACHED_CONNECTION_FAILED:
                    $this->_forward('exception-memcached-connection-failed');
                    break;
                    
                  default:
                    $this->view->message = 'Exception caught (' . get_class($errors->exception) . '), but no specific handler in ErrorHandler defined';
                    $this->view->exception = $errors->exception;
                    break;
                  }
                  
                  // Log only when it's something different from 404
                  $this->getLog()->emerg($this->getRequest()->getRequestUri() . '|' . $errors->exception->getCode() . '|' . $errors->exception->getMessage());

                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->message = 'Application error 500';
                break;
        }
        
        // conditionally display exceptions
        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }
        
        $this->view->request = $errors->request;
    }

    public function getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasResource('Log')) {
            return false;
        }
        $log = $bootstrap->getResource('Log');
        return $log;
    }
    
    public function exceptionDbConnectionFailedAction()
    {
      $this->view->message = 'Nie udało się połączyć z bazą danych...';
      $this->renderScript('error/error.phtml');
    }
    
    public function exceptionMemcachedConnectionFailedAction()
    {
      $this->view->message = 'Nie udało się połączyć z Memcached.';
      $this->renderScript('error/error.phtml');
    }
}