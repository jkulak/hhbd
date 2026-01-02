<?php

class UserController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->_request = $this->getRequest();
  }

  public function indexAction()
  {

  }
  
  public function viewAction()
  {
    $id = $this->_request->get('id');
    $this->view->user = Model_User::getInstance()->findById($id);
  }
  
  public function registrationAction()
  {
    // if post data received, it's user creation
    if ($this->getRequest()->isPost()) {
      $this->_registerUser();
    }
    else
    {
      // if is registered - move to homesite
      if (Zend_Auth::getInstance()->hasIdentity()) {
        $this->_redirect($this->view->url(array(), 'homeSite'));
      }
    }
  }
  
  /**
   * Loggin user in
   *
   * @return boolean
   * @since 2011-02-06
   * @author Kuba
   * @file: UserController.php
   **/
  private function _loginUser($email, $password)
  {
    $result = false;
    
    $db = Zend_Db_Table::getDefaultAdapter();
    $authAdapter = new Zend_Auth_Adapter_DbTable($db);
    $authAdapter->setTableName('hhb_users');
    $authAdapter->setIdentityColumn('usr_email');
    $authAdapter->setCredentialColumn('usr_password');
    $authAdapter->setCredentialTreatment('MD5(?)');

    $authAdapter->setIdentity($email);
    $authAdapter->setCredential($password . Model_User::$passwordSalt);

    $auth = Zend_Auth::getInstance();
    $result = $auth->authenticate($authAdapter);

    // Did the participant successfully login?
    if ($result->isValid()) {
      // retrive user data needed in the front
      $data = $authAdapter->getResultRowObject(array('usr_display_name', 'usr_id', 'usr_is_admin', 'usr_login_count'));
      $auth->getStorage()->write($data);
      // save last login info
      Model_User::getInstance()->update(array('usr_last_login' => date('Y-m-d H:i:s'), 'usr_login_count' => $data->usr_login_count + 1),
                                        'usr_email="' . $email . '"');
      $result = true;
    }
    return $result;
  }
  
  public function loginAction()
  {
    $errors = array();
    if ($this->getRequest()->isPost()) {
      $email = $this->_request->getPost('email');
      $password = $this->_request->getPost('password');
      if (empty($email) || empty($password))
      {
        $errors['login'][] = "Podaj adres e-mail i hasło i spróbuj jeszcze raz.";
      }
      else
      {
        if ($this->_loginUser($email, $password)) {
          $url = $this->getRequest()->getPost('url');
          $this->_redirect($url); 
        } else {
          $errors['login'][] = "Podane dane do logowania nie są poprawne. Popraw je i spróbuj jeszcze raz.";
        }
      }
    }
    else
    {
      if (Zend_Auth::getInstance()->hasIdentity()) {
        $this->_redirect('/');
      }
      /*
        TODO this is blah blah bad, no redirects to user defined websites!!!
        to be fixed, it's a great vulnerability
        bug: http://code.google.com/p/hhbdevolution/issues/detail?id=13
      */
      $this->view->redirectUrl = $this->getRequest()->getParam('url');
    }
    $this->view->errors = $errors;
  }
  
  public function logoutAction()
  {
    Zend_Auth::getInstance()->clearIdentity();
    $this->_redirect('/');
  }
  
  /**
   * Displays information that user needs to be logged in to perform this action/view this site
   *
   * @author Kuba
   **/
  public function notLoggedInAction()
  {
    
  }
  
  
  private function _registerUser()
  {
    $result = Model_User::getInstance()->save($this->_request->getPost());
    
    if (! is_array($result)) {
      $this->view->user = Model_User::getInstance()->findById($result);
      $this->view->created = 1;
      
      // after registration, login user automatically
      $this->_loginUser($this->_request->getPost('email'), $this->_request->getPost('password'));
    } else {
      $this->view->errors = $result;
      // $this->view->firstName = $this->_request->getPost('first-name');
      // $this->view->lastName = $this->_request->getPost('last-name');
      $this->view->email = $this->_request->getPost('email');
      $this->view->password = $this->_request->getPost('password');
      $this->view->displayName = $this->_request->getPost('display-name');
    }
  }
}