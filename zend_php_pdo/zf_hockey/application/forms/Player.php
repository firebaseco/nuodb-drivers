<?php

class Application_Form_Player extends Zend_Form
{

    public function init()
    {
	$this->setName('player');
	$id = new Zend_Form_Element_Hidden('id');
	$id->addFilter('Int');
	$number = new Zend_Form_Element_Text('number');
	$number->setLabel('Number')
		->setRequired(true)
		->addFilter('StripTags')
		->addFilter('StringTrim')
		->addValidator('NotEmpty');
	$name = new Zend_Form_Element_Text('name');
	$name->setLabel('Name')
		->setRequired(true)
		->addFilter('StripTags')
		->addFilter('StringTrim')
		->addValidator('NotEmpty');
	$position = new Zend_Form_Element_Text('position');
	$position->setLabel('Position')
		->setRequired(true)
		->addFilter('StripTags')
		->addFilter('StringTrim')
		->addValidator('NotEmpty');
	$team = new Zend_Form_Element_Text('team');
	$team->setLabel('team')
		->setRequired(true)
		->addFilter('StripTags')
		->addFilter('StringTrim')
		->addValidator('NotEmpty');

	$submit = new Zend_Form_Element_Submit('submit');
	$submit->setAttrib('id', 'submitbutton');

	$this->addElements(array($id, $number, $name, $position, $team, $submit));
    }
}

