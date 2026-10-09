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
                // A page that does not exist is the visitor's mistake or a crawler's, not the
                // site's: an info line at most (#101).
                $this->log(Zend_Log::INFO, 'No such page', $errors->exception, 404);
                $this->view->title = 'Błąd 404: Nieeee, nie mamy takiej strony (jeszcze)!';
                $this->view->message = 'Ale nie przejmuj się tym, to nie Twoja wina :) Sprawdź czy wpisałeś dobry adres, a najlepiej chodź na <a href="/">stronę główną</a> lub wpisz czego szukasz w naszej wyszukiwarce!';
                break;
                
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER:
                $this->getResponse()->setHttpResponseCode(500);
                switch($errors->exception->getCode()) {
                  
                  case 2002:
                    $this->_forward('exception-db-connection-failed');
                    break;

                  default:
                    $this->view->message = 'Exception caught (' . get_class($errors->exception) . '), but no specific handler in ErrorHandler defined';
                    $this->view->exception = $errors->exception;
                    break;
                  }
                  
                  $this->log(Zend_Log::ERR, 'Request failed', $errors->exception, 500);

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
        $this->describe();
    }

    /**
     * The error page's title, in the site's layout, and nothing in it for a search engine to
     * keep: it was "Zend Framework Default Application", in a second document inside the first,
     * and the layout's index,follow (#147). The whole title, as the controller that failed may
     * have put its own part in already.
     */
    private function describe()
    {
        $this->view->headTitle()->set((404 === $this->getResponse()->getHttpResponseCode() ? 'Nie ma takiej strony' : 'Błąd') . ' - Hhbd.pl');
        $this->view->headMeta()->setName('robots', 'noindex,follow');
    }

    /**
     * One line for the failed request (#101): its path and status, and the exception as
     * `error` and `stack`; the formatter adds the time, the level and the edge's request id.
     */
    private function log($priority, $msg, $exception, $status)
    {
        $log = $this->getLog();
        if (!$log) {
            return;
        }
        $fields = array('path' => (string) $this->getRequest()->getRequestUri(), 'status' => $status);
        if ($exception instanceof Throwable) {
            $fields['exception'] = $exception;
        }
        $log->log($msg, $priority, $fields);
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
}