<?php

define('MAX_COMMENT_LENGTH', 1000);
define('MIN_FORM_TIME_SECONDS', 2);  // Minimum time to fill form (bot protection)
define('CAPTCHA_SALT', 'hhbd_salt_2024');

class CommentController extends Zend_Controller_Action
{
    public function init()
    {
        $this->params = $this->getRequest()->getParams();
    }

    public function indexAction()
    {
        $content = htmlentities($this->params['content'], ENT_COMPAT, "UTF-8");

        $authorId = null;

        if (Zend_Auth::getInstance()->hasIdentity()) {
            $user = Zend_Auth::getInstance()->getIdentity();
            $author = $user->usr_display_name;
            $authorId = $user->usr_id;
        } else {
            if (isset($this->params['author'])) {
                $author = htmlentities('~' . $this->params['author'], ENT_COMPAT, "UTF-8");
            } else {
                $author = '~';
            }
        }

        $authorIp = $_SERVER['REMOTE_ADDR'];
        $objectId = $this->params['com_object_id'];
        $objectType = $this->params['com_object_type'];
        $honeyPotValue = $this->params['email-honey-pot'];

        // Verify Honey Pot spam
        if (strlen($honeyPotValue) > 0) {
            // check if filter is working by loggin those requestes
            Zend_Registry::get('Logger')->info('Spamer? >' . $honeyPotValue . '< - content: ' . $content . ' - author: ' . $author);

            // it's just spam, return
            return;
        }

        // Verify timestamp (bot detection - forms submitted too quickly)
        $formTime = isset($this->params['form_time']) ? (int)$this->params['form_time'] : 0;
        $timeDiff = time() - $formTime;
        if ($formTime > 0 && $timeDiff < MIN_FORM_TIME_SECONDS) {
            Zend_Registry::get('Logger')->info('Bot detected (too fast): ' . $timeDiff . 's - content: ' . $content);
            return;
        }

        // Verify CAPTCHA for anonymous users
        $captchaFailed = false;
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $captchaAnswer = isset($this->params['captcha_answer']) ? trim($this->params['captcha_answer']) : '';
            $captchaHash = isset($this->params['captcha_hash']) ? $this->params['captcha_hash'] : '';
            $expectedHash = md5($captchaAnswer . CAPTCHA_SALT);

            if (empty($captchaAnswer) || $expectedHash !== $captchaHash) {
                Zend_Registry::get('Logger')->info('CAPTCHA failed - answer: ' . $captchaAnswer . ' - author: ' . $author);
                $captchaFailed = true;
            }
        }

        switch ($this->params['com_object_type']) {
            case 'a':
                $entity = Model_Album_Api::getInstance()->find($this->params['com_object_id']);
                if ($entity) {
                    $redirect = Jkl_Tools_Url::createUrl($entity->artist->name . '+-+' . $entity->title . '-a' . $entity->id . '.html');
                }
                break;

            case 'p':
            case 'wykonawca':
                $entity = Model_Artist_Api::getInstance()->find($this->params['com_object_id']);
                if ($entity) {
                    $redirect = Jkl_Tools_Url::createUrl($entity->name . '-p' . $entity->id . '.html');
                }
                break;

            case 'l':
            case 'wytwornia':
                $entity = Model_Label_Api::getInstance()->find($this->params['com_object_id']);
                if ($entity) {
                    $redirect = Jkl_Tools_Url::createUrl($entity->name . '-l' . $entity->id . '.html');
                }
                break;

            case 's':
            case 'utwor':
                $entity = Model_Song_Api::getInstance()->find($this->params['com_object_id']);
                if ($entity) {
                    $redirect = Jkl_Tools_Url::createUrl($entity->title . '-s' . $entity->id . '.html');
                }
                break;

            case 'n':
            case 'news':
                $entity = Model_News_Api::getInstance()->find($this->params['com_object_id']);
                if ($entity) {
                    $redirect = Jkl_Tools_Url::createUrl($entity->title . '-n' . $entity->id . '.html');
                }
                break;

            default:
                break;
        }

        // return error for non ajax request
        //if  (!$this->getRequest()->isXmlHttpRequest()) {
        //}

        // verify if content not empty
        if (empty($content)) {
            $redirect .= '?postError=1&emptyComment=1#comments';
            $this->_redirect('/' . str_replace(' ', '+', $redirect));
            return;
        }

        // verify if content not too long
        if (strlen($content) > MAX_COMMENT_LENGTH) {
            $redirect .= '?postError=1&commentTooLong=1#comments';
            $this->_redirect('/' . str_replace(' ', '+', $redirect));
            return;
        }

        // verify CAPTCHA (redirect with error so user can retry)
        if ($captchaFailed) {
            $redirect .= '?postError=1&captchaError=1#comments';
            $this->_redirect('/' . str_replace(' ', '+', $redirect));
            return;
        }

        $result = Model_Comment_Api::getInstance()->postComment($content, $author, $authorIp, $objectId, $objectType, $authorId);

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->json(array('content' => $content, 'author' => $author, 'authorId' => $authorId));
        } else {
            if (isset($entity) && $entity) {
                // data validation
                if (!$result) {
                    $redirect .= '?postError=1&dbError=1';
                }
                $this->_redirect('/' . str_replace(' ', '+', $redirect . '#comments'));
                return;
            } else {
                $this->getResponse()->setHttpResponseCode(404);
                $this->_redirect('/404.html');
                return;
            }
        }
    }

    public function viewAction()
    {
    }
}
