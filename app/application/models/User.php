<?php

class Model_User extends Zend_Db_Table_Abstract
{

  static private $_instance;

  // db table name
  protected $_name = 'hhb_users';

  /**
   * Singleton instance
   *
   * @return Model_Search_Api
   */
  public static function getInstance()
  {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }
      return self::$_instance;
  }

  /**
   * Creates object and fetches the list from database result
   */
  private function _getList($query)
  {
    $result = $this->_db->fetchAll($query);;
    $list = new Jkl_List('User list');
    foreach ($result as $params) {
      // conversion to stdObject, because blah :/ Model_UserContainer takes objects as parameters
      $list->add(new Model_User_Container((object)$params));
    }
    return $list;
  }

  /*
  * Validate and save user into db
  */
  public function save(array $data)
  {
    // Initialize the errors array
    $errors = array();

    // Display name validation
    $validAlnum = new Zend_Validate_Alnum();
    $validAlnum->setMessage(
        'Możesz używać tylko liter i cyfr.',
        Zend_Validate_Alnum::NOT_ALNUM
    );

    $validLength = new Zend_Validate_StringLength(3, 30);
    $validLength->setMessage(
        "Wpisz co najmniej %min% znaki.",
        Zend_Validate_StringLength::TOO_SHORT
    );
    $validLength->setMessage(
        "Wpisz maksymalnie %max% znaków.",
        Zend_Validate_StringLength::TOO_LONG
    );

    $validatorChain = new Zend_Validate();
    $validatorChain->addValidator($validLength)->addValidator($validAlnum);

    if (! $validatorChain->isValid($data['display-name'])) {
        foreach ($validatorChain->getMessages() as $message) {
            $errors['displayName'][] = $message;
        }
    }


    //
    // $validDisplayName = new Zend_Validate_Alnum();
    //  if (! $validDisplayName->isValid($data['display-name'])) {
    //      $errors['displayName'][] = "Nazwa może składać się tylko z liter i cyfr i _.";
    //   }
    //   else
    //   {
    //     $validDisplayName = new Zend_Validate_StringLength(3, 30);
    //     if (! $validDisplayName->isValid($data['display-name'])) {
    //        $errors['displayName'][] = "Twoja nazwa musi mieć od 6 do 20 znaków.";
    //     }
    //     else
    //     {
    //       $result = $this->findByDisplayName($data['display-name']);
    //       if (count($result) > 0) {
    //         $errors['displayName'][] = "Ktoś już używa takiej nazwy, wpisz inną.";
    //       }
    //     }
    //   }

    // First Name
    // if (! Zend_Validate::is($data['first-name'], 'NotEmpty')) {
    //    $errors['fistName'][] = "Please provide your first name.";
    // }
    //
    // // Last Name
    // if (! Zend_Validate::is($data['last-name'], 'NotEmpty')) {
    //    $errors['lastName'][] = "Please provide your last name.";
    // }


    if (Zend_Validate::is($data['email'], 'EmailAddress')) {
      // Does Email already exist?
     $result = $this->findByEmail($data['email']);
     if ($result) {
       $errors['email'][] = "Konto dla tego adresu e-mail już istnieje, podaj inny";
     }
    } else {
     $errors['email'][] = "Podaj poprawny adres e-mail.";
    }

    // Password must be at least 6 characters, and at most bcrypt's 72 bytes' worth (#41)
    $validLength->setMin(6);
    $validLength->setMax(72);
    $validLength->setMessage(
        "Wpisz co najmniej %min% znaków.",
        Zend_Validate_StringLength::TOO_SHORT
    );

    if (! $validLength->isValid($data['password'])) {
      foreach ($validLength->getMessages() as $message) {
          $errors['password'][] = $message;
      }
       // $errors['password'][] = "Twoje hasło musi mieć od 6 do 20 znaków.";
    }

    // If no errors, insert the
    if (count($errors) == 0) {
      $data = array (
        'usr_display_name' => $data['display-name'],
        'usr_email' => $data['email'],
        'usr_password' => Model_User_Password::hash($data['password']),
        'usr_added' => date('Y-m-d H:i:s')
        );
        $result = $this->insert($data);
      return $result;
    }
    else
    {
      return $errors;
    }
  }

  public function findByEmail($email)
  {
    $email = strval($email);

    $result = $this->fetchAll($this->_db->quoteInto('usr_email = ?', $email));
    if (count($result) > 0) {
      return new Model_User_Container($result->current());
    }
    else
    {
      return false;
    }

  }

  /**
   * The account of that e-mail and password, as the session keeps it, or null (#41). A login
   * counts, and a password hashed the old way, or at an old cost, gets a new hash.
   *
   * @return object|null usr_id, usr_display_name, usr_is_admin, usr_login_count
   */
  public function authenticate($email, $password)
  {
    $row = $this->_db->fetchRow(
      'SELECT usr_id, usr_password, usr_display_name, usr_is_admin, usr_login_count FROM hhb_users WHERE usr_email = ? LIMIT 1',
      array((string) $email)
    );
    if (!$row || !Model_User_Password::verify($password, $row['usr_password'], Model_User_Password::legacySalt())) {
      return null;
    }
    $set = array('usr_last_login' => date('Y-m-d H:i:s'), 'usr_login_count' => $row['usr_login_count'] + 1);
    if (Model_User_Password::needsRehash($row['usr_password'])) {
      $set['usr_password'] = Model_User_Password::hash($password);
    }
    $this->update($set, array('usr_id = ?' => (int) $row['usr_id']));
    unset($row['usr_password']);
    return (object) $row;
  }

  public function findByDisplayName($name)
  {
    $name = strval($name);

    $rows = $this->fetchAll($this->_db->quoteInto('usr_display_name = ?', $name));
    return $rows;
  }

  public function findById($id)
  {
    $id = intval($id);
    $result = $this->find($id);
    return new Model_User_Container($result->current());
  }

  /**
   * Returns list of users that edited lyrics of selected song
   *
   * @return Jkl_List of Model_User_Container
   * @since 2011-02-03
   * @author Kuba
   **/
  public function getLyricsEditors($songId)
  {
    $query = "SELECT DISTINCT(t1.usr_id), t1.usr_display_name, t1.usr_email, t2.ule_action_timestamp
              FROM hhb_users t1, hhb_user_lyrics_edit t2
              WHERE (t1.usr_id=t2.ule_user_id AND t2.ule_lyrics_id='" . $songId . "') ORDER BY t2.ule_action_timestamp ASC
              ;";
    return self::_getList($query);
  }
}
