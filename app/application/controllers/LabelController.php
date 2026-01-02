<?php

class LabelController extends Zend_Controller_Action
{
  
  public function init()
  {
    $this->view->headMeta()->setName('keywords', 'wytwórnia,polski hip-hop,lista,polskie wytwórnie');
    $this->view->headTitle()->headTitle('Lista wytwórni hip-hopowych - Hhbd.pl', 'PREPEND');
    $this->view->headMeta()->setName('description', 'Wytwórnie w hhbd.pl');
    $this->params = $this->getRequest()->getParams();
  }

  public function indexAction()
  {
    $this->view->labels = Model_Label_Api::getInstance()->getFullList();
    $this->view->withMostAlbums = Model_Label_Api::getInstance()->getWithMostAlbums();

    $labelKeywords = '';
    for ($i=0; $i < 5; $i++) { 
      $labelKeywords .= $this->view->withMostAlbums->items[$i]->name . ',';
    }
    
    $this->view->headTitle()->set('Lista wytwórni hip-hopowych - Hhbd.pl');
    $this->view->headMeta()->setName('keywords', $labelKeywords . 'wytwórnia,polski hip-hop,lista,polskie wytwórnie');
    $this->view->headMeta()->setName('description', 'Lista polskich wytwórni, wydających hip-hop, polski hip-hop.');
  }
  
  public function viewAction()
  {
    $params = $this->getRequest()->getParams();
    $label = Model_Label_Api::getInstance()->find($params['id'], true);
    $label->releases = Model_Album_Api::getInstance()->getLabelReleases($label->id, null);
    $artists = array();
    foreach ($label->releases->items as $key => $value) {
      $artists[] = $value->artist->name;
    }
    $label->artists = implode(', ', array_unique($artists));
    $label->autoDescription = $this->_generateDescription($label);
    $this->view->label = $label;
    
    $this->view->comments = Model_Comment_Api::getInstance()->getComments($label->id, Model_Comment_Container::TYPE_LABEL);
  
    $this->view->withMostAlbums = Model_Label_Api::getInstance()->getWithMostAlbums();
    
    $this->view->headTitle()->set($label->name . ' - Hhbd.pl');
    $this->view->headMeta()->setName('keywords', $label->name . ',' . implode(',', $artists) . ',wytwórnia,polski hip-hop');
    $this->view->headMeta()->setName('description', $label->name . ' to wytwórnia wydająca polski hip-hop, w jej szeregach są tacy artyści jak: ' . implode(', ', $artists));
  }
  
  // description autogeneration, displayedfor SEO purposes
  private function _generateDescription($label)
  {
    $description = '';
    
    $description .= $label->name . ' wydała do tej pory ' . count($label->releases->items) . ' ';
    $description .= ((count($label->releases->items)>4)?'albumów':((count($label->releases->items)>1)?'albumy':'album')) . '. ';
    $description .= 'Wytwórnia wydaje taki artystów jak: ' . $label->artists . '. ';
    $description .= 'Najnowsze wydawnictwo wytwórni to: ' . $label->releases->items[0]->title . ', ';
    $description .= 'za które odpowiedizalny jest ' . $label->releases->items[0]->artist->name . '. ';
    $description .= 'Ostatni album ' . $label->name . ' wydało ' . $label->releases->items[0]->releaseDateNormalized . '. ';
    
    if (!empty($label->website)) {
      $description .= 'Oficjalna strona wytwórni, to: ' . $label->website . ' - sprawdzajcie! ';
    }
    
    if (!empty($label->email)) {
      $description .= 'Jeżeli chcecie się skontaktować bezpośrednio z wytwórnią, to piszcie na adres: ' . $label->email . '. ';
    }
    
    if (!empty($label->addres)) {
      $description .= 'Pocztowy adres wytwórni to: ' . $label->addres . ' - możecie słać demówki!';
    }
    
    return $description;
  }
}