<?php

/**
 * X-Query-Count: the statements a page sent to the database, once the layout has rendered it,
 * so a test can hold each page to a few queries rather than several per row (#255). Bootstrap
 * registers it only where QUERY_COUNT_HEADER is set, locally and in CI.
 */
class Jkl_Controller_Plugin_QueryCount extends Zend_Controller_Plugin_Abstract
{
    public function dispatchLoopShutdown()
    {
        $this->getResponse()->setHeader('X-Query-Count', (string) Jkl_Db::statementCount(), true);
    }
}
