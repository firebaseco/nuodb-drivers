<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
	$hockey = new Application_Model_DbTable_Hockey();
	$this->view->hockey = $hockey->fetchAll();
    }

    public function addAction()
    {
	$form = new Application_Form_Player();
	$form->submit->setLabel('Add');
	$this->view->form = $form;
	if ($this->getRequest()->isPost()) {
	   $formData = $this->getRequest()->getPost();
	   if ($form->isValid($formData)) {
	      $number = $form->getValue('number');
	      $name = $form->getValue('name');
	      $position = $form->getValue('position');
	      $team = $form->getValue('team');
	      $hockey = new Application_Model_DbTable_Hockey();
 	      $hockey->addPlayer($number, $name, $position, $team);
	      $this->_helper->redirector('index');
       	   } else {
              $form->populate($formData);
       	   }
       }
    }

    public function editAction()
    {
	$form = new Application_Form_Player();
	$form->submit->setLabel('Save');
	$this->view->form = $form;

	if ($this->getRequest()->isPost()) {
	   $formData = $this->getRequest()->getPost();
	   if ($form->isValid($formData)) {
	      $id = (int)$form->getValue('id');
	      $number = $form->getValue('number');
	      $name = $form->getValue('name');
	      $position = $form->getValue('position');
	      $team = $form->getValue('team');
	      $hockey = new Application_Model_DbTable_Hockey();
 	      $hockey->updatePlayer($id, $number, $name, $position, $team);
	      $this->_helper->redirector('index');
	   } else {
	      $form->populate($formData);
	   }
	} else {
	  $id = $this->_getParam('id', 0);
	  if ($id > 0) {
	     $hockey = new Application_Model_DbTable_Hockey();
	     $form->populate($hockey->getPlayer($id));
	  }
       }
    }

    public function deleteAction()
    {
	if ($this->getRequest()->isPost()) {
	   $del = $this->getRequest()->getPost('del');
	   if ($del == 'Yes') {
	      $id = $this->getRequest()->getPost('id');
	      $hockey = new Application_Model_DbTable_Hockey();
	      $hockey->deletePlayer($id);
	   }
	   $this->_helper->redirector('index');
	} else {
	  $id = $this->_getParam('id', 0);
	  $hockey = new Application_Model_DbTable_Hockey();
	  $this->view->hockey = $hockey->getPlayer($id);
        }
    }


}







