<?php
namespace saitho\WatajaxDoctrine;
use Doctrine\Common\Collections\Collection;

/**
 * Class WatajaxDoctrine
 * by Mario Lubenka
 * @see https://github.com/saitho/watajax-doctrine
 *
 * Licensed under Apache License 2.0
 * @package saitho\WatajaxDoctrine
 */

// WatajaxSql.php from Watajax source has to be included!
// require_once(__DIR__.'/WatajaxSql.php');

class WatajaxDoctrine extends \Watajax {
	
	protected $em, $qb;
	protected $tables = [];
	
	protected $table = "";
	protected $encoding = "UTF-8";
	protected $where = "";
	
	
	public function __construct(\Doctrine\ORM\EntityManager $entityManager) {
		//parent::__construct():
		$this->perPage = (!empty($_GET['watajax_per_page']) && is_numeric($_GET['watajax_per_page'])) ? intval($_GET['watajax_per_page']) : 10;
		$this->page = (!empty($_GET['watajax_page']) && is_numeric($_GET['watajax_page'])) ? intval($_GET['watajax_page']) : 1;
		$this->sortBy = !empty($_GET['watajax_sortBy']) ? $_GET['watajax_sortBy'] : null;
		$this->sortOrder = !empty($_GET['watajax_sortOrder']) ? $_GET['watajax_sortOrder'] : 'ASC';
		$this->search = !empty($_GET['watajax_search']) ? $_GET['watajax_search'] : '';
		
		$this->table = $_GET['watajax_table'];
		
		$this->em = $entityManager;
		$this->qb = new \Doctrine\ORM\QueryBuilder($this->em);
	}
		
	public function searchFilterData() {
		if ($this->search != "") {
			$where = "";
			foreach($this->columns as $key => $value) {
				if (empty($value["virtual"])) {
					$where .= "`$key` LIKE '%".$this->search."%' OR ";
				}
			}
			$where = "(".rtrim($where, " OR ").")";
			$this->where = $where;
		}
	}
	
	public function sortData() {
		$virtualSorting = false;
		foreach($this->columns as $key => $value) {
			if (!empty($value['virtual'])) {
				if(!empty($value['dqlSortValue']) && !empty($value['dqlSortFunc']) && $this->sortBy == $key) {
					$sortReference = 'id';
					if(!empty($value['dqlSortReference'])) {
						$sortReference = $value['dqlSortReference'];
					}
					$this->qb->addSelect($value['dqlSortFunc'].'(b) AS '.$key);
					$this->qb->join('a.'.$value['dqlSortValue'], 'b');
					$this->qb->groupBy('a.'.$sortReference);
					$virtualSorting = true;
				}
			}
		}
		
		if(!empty($this->sortBy)) {
			if(!$virtualSorting) {
				$this->sortBy = 'a.'.$this->sortBy;
			}
			$this->qb->orderBy($this->sortBy, $this->sortOrder);
		}
	}
	
	private function getReplaceValues($col, $row) {
		$values = [];
		if(!empty($this->columns[$col]['dqlModelValue'])) {
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			
			foreach($this->columns[$col]['dqlModelValue'] AS $var => $classVar) {
				$value = $object;
				$split = explode('->', $classVar);
				foreach($split AS $item) {
					$optionSplit = explode(':', $item);
					$name = $optionSplit[0];
					$options = !empty($optionSplit[1]) ? $optionSplit[1] : '';
					
					$getterName = 'get'.ucfirst($this->camelCase($name));
					if(!is_object($value)) {
						throw new \Exception('Error at dqlModelValue transformation: not object as expected.');
					}else if(!method_exists($value, $getterName)) {
						throw new \Exception('Error at dqlModelValue transformation: Method '.$getterName.' does not exist.');
					}
					$value = $value->$getterName();
					if(!is_object($value) && !empty($options)) {
						switch($options) {
							case 'lower':
								$value = strtolower($value);
								break;
							case 'upper':
								$value = strtoupper($value);
								break;
							case 'camel':
								$value = $this->camelCase($value);
								break;
						}
					}
				}
				$values['!'.$var] = $value;
			}
		}
		return $values;
	}
		
	public function transformColumn($col, $data, $row) {
		if(is_string($data) && $this->encoding == "UTF-8") {
			$data = utf8_encode($data);
		}
		if (empty($this->columns[$col]["transform"])) {
			return $data;
		}
		
		$replaceValues = $this->getReplaceValues($col, $row);
		$replace = array_keys($replaceValues);
		$replaceRow = array_values($replaceValues);
				
		foreach(array_keys($this->columns) as $k) {
			$getterName = 'get'.ucfirst($this->camelCase($k));
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			
			if(is_array($row) && array_key_exists($k, $row) && (!is_object($row[0]) || !method_exists($row[0], $getterName))) {
				$value = $row[$k];
			}elseif(is_object($object)) {
				if(!method_exists($object, $getterName)) {
					continue;
				}
				$value = $object->$getterName();
			}else{
				continue;
			}
			if(is_object($value)) {
				continue;
			}
			$replaceRow[] = $value;
			$replace[] = "!".$k;
		}
		$data = str_replace($replace, $replaceRow, $this->columns[$col]["transform"]);
		return $data;
	}
	
	public function addTable($modelClass) {
		if(!in_array($modelClass, $this->tables)) {
			$this->tables[] = $modelClass;
		}
	}
	public function getTable($index) {
		$arrayIndex = ($index-1);
		if(empty($this->tables[$arrayIndex])) {
			throw new \Exception('No table found at index '.$index.' (index in array is '.$arrayIndex.').');
		}
		return $this->tables[$arrayIndex];
	}
	public function getCurrentTable() {
		return $this->getTable($this->table);
	}
	private function camelCase($string) {
		$camelCase = '';
		$split = preg_split('/[^A-Za-z]/', $string);
		foreach($split AS $item) {
			$camelCase .= ucfirst($item);
		}
		return lcfirst($camelCase);
	}
	public function getData() {
		
		$data = array();
		$limit_start = (($this->page-1)*$this->perPage);
		
		$repo = $this->em->getRepository($this->getCurrentTable());
		//$result = $repo->findBy([], $sortBy, $this->perPage, $limit_start);
		
		
		$dql = $this->qb->getDQL();
		$query = $this->em->createQuery($dql)
			->setFirstResult($limit_start)
			->setMaxResults($this->perPage);
		$result = $query->execute();
				
		foreach($result AS $row) {
			$object = $row;
			if(is_array($row) && is_object($row[0])) {
				$object = $row[0];
			}
			$fixed_row = array();
			foreach($this->columns as $key => $value) {
				$rowValue = '';
				$getterName = 'get'.ucfirst($this->camelCase($key));
				if(method_exists($object, $getterName)) {
					$rowValue = $object->$getterName();
				}
				$fixed_row[$key] = $this->transformColumn($key, $rowValue, $row);
			}
			$data[] = $fixed_row;
		}
		return $data;
	}
	
	function getNumberOfPages() {
		$qb = $this->em->createQueryBuilder();
		$queryBuild = $qb->select('a')->from($this->getCurrentTable(), 'a');
		if(!empty($this->where)) {
			$queryBuild->where($this->where);
		}
		$dql = $queryBuild->getDQL();
		$query = $this->em->createQuery($dql);
		$result = $query->execute();
		$num = count($result);
		return (is_numeric($num)) ? ceil($num / $this->perPage) : 0;
	}
	
	function sendBody() {
		$this->qb->select('a')->from($this->getCurrentTable(), 'a');
		$this->sortData();
		$this->searchFilterData();
		$sorted_data = $this->getData();
		
		foreach($sorted_data as $row_id => $row_data) {
			echo "<tr id='$row_id'>";
			foreach($this->columns as $column_id => $column_data) {
				if (empty($this->columns[$column_id]["hide"])) {
					echo "<td id='".$column_id."_data'>".$row_data[$column_id]."</td>";
				}
			}
			echo "</tr>";
		}
	}
	
	private function fixColumns() {
		foreach($this->columns as $id => &$data) {
			if (empty($data["hide"])) {
				$data["hide"] = false;
			}
		}
	}
	function sendHead() {
		// fix Notice for missing $data["hide"]
		$this->fixColumns();
		parent::sendHead();
	}
}