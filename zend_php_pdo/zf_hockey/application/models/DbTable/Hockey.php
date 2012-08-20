<?php

class Application_Model_DbTable_Hockey extends Zend_Db_Table_Abstract
{

    protected $_name = 'hockey';

    public function getPlayer($id)
    {
	$id = (int)$id;
	$row = $this->fetchRow('id = ' . $id);
	if (!$row) {
	   throw new Exception("Could not find row $id");
    	}
    	return $row->toArray();
    }

    public function addPlayer($number, $name, $position, $team)
    {
	$data = array(
	      'number' => $number,
	      'name' => $name,
	      'position' => $position,
	      'team' => $team
	      );
	$this->insert($data);
    }

    public function updatePlayer($id, $number, $name, $position, $team)
    {
	$data = array(
	      'number' => $number,
	      'name' => $name,
	      'position' => $position,
	      'team' => $team
	      );
	$this->update($data, 'id = '. (int)$id);
    }

    public function deletePlayer($id)
    {
	$this->delete('id =' . (int)$id);
    }

}

