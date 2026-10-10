<?php

define('MAX_COMMENT_LENGTH', 1000);
define('MIN_FORM_TIME_SECONDS', 2);  // Minimum time to fill form (bot protection)

#[\AllowDynamicProperties]
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

        // An anonymous comment answers the question the server holds, once (#41)
        $captchaFailed = false;
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $captchaAnswer = isset($this->params['captcha_answer']) ? trim($this->params['captcha_answer']) : '';
            $captchaToken = isset($this->params['captcha_token']) ? $this->params['captcha_token'] : '';
            if (!Model_Captcha_Api::getInstance()->check($captchaToken, $captchaAnswer)) {
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
                    $redirect = Jkl_Tools_Url::createUrl($entity->qualifiedName . '-p' . $entity->id . '.html');
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

        // The script gets what went wrong as JSON, to say it and ask a new question (#41);
        // a form sent without one gets the page back with the error
        $refusal = null;
        if (empty($content)) {
            $refusal = 'Komentarz nie może być pusty.';
        } elseif (strlen($content) > MAX_COMMENT_LENGTH) {
            $refusal = 'Komentarz jest za długi (maksymalnie 1000 znaków).';
        } elseif ($captchaFailed) {
            $refusal = 'Nieprawidłowa odpowiedź na pytanie zabezpieczające. Odpowiedz na nowe pytanie.';
        }
        if (null !== $refusal && $this->getRequest()->isXmlHttpRequest()) {
            $this->getResponse()->setHttpResponseCode(422);
            $this->_helper->json(array('error' => $refusal, 'captcha' => $captchaFailed));
            return;
        }

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

    /**
     * A new question for the comment form, as JSON: the token its answer is kept under and the
     * question (#41). The script asks when someone starts a comment. A POST, so no crawler and
     * no cache makes one.
     */
    public function captchaAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405)->setHeader('Allow', 'POST');
            $this->_helper->json(array('error' => 'POST only'));
            return;
        }
        $this->getResponse()->setHeader('Cache-Control', 'no-store', true);
        $this->_helper->json(Model_Captcha_Api::getInstance()->create());
    }

    public function viewAction()
    {
    }
}
