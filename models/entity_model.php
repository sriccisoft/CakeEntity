<?php

App::import('Model', 'Entity.Entity');

if (!App::import('Model', 'AppEntity')) {
	App::import('Core', 'Entity.AppEntity');
}

class EntityModel extends EntityAppModel {
	public $entity;
	protected $savedEntityStates = array();
	
	/*
	 *	Convert passed $data structure into coresponding entity object.
	 *	@param $data Hash to be converted. If omitted, $this->data will be converted.
	 *	@returns Entity object
	 */
	protected function convertToEntity($data) {
		if (is_null($data) or empty($data[$this->name]['id'])) return null;
		
		return $this->entity($data);
	}
	
	protected function convertToEntities($list_of_data) {
		if (!Set::numeric(array_keys($list_of_data))) {
			return $this->convertToEntity($list_of_data);
		}
		
		$result = array();
		foreach ($list_of_data as $data) {
			$result[] = $this->convertToEntity($data);
		}
		return $result;
	}
	
	public function entity($data = array()) {
		if ($data) {
			$class = $this->entityClassForData($data[$this->alias]);
		} else {
			$class = $this->entityClass();
		}
		
		if (!class_exists($class)) {
			if (!App::import('Model', $class)) {
				$class = 'AppEntity';
			}
		}
		
		$entity = new $class();
		$entity->init($this, $data);
		return $entity;
	}
	
	public function beforeFind($queryData) {
		$this->saveEntityState();
		
		if (isset($queryData['entity'])) {
			$this->entity = $queryData['entity'];
		}
		
		return parent::beforeFind($queryData);
	}
	
	public function afterFind($result, $primary) {
		$result = parent::afterFind($result, $primary);
		
		if ($this->entity and $primary and is_array($result)) {
			$result = $this->convertToEntities($result);
		}
		
		$this->restoreEntityState();
		return $result;
	}
	
	protected function saveEntityState() {
		$this->savedEntityStates[] = $this->entity;
	}
	
	protected function restoreEntityState() {
		$this->entity = array_pop($this->savedEntityStates);
	}
	
	protected function entityClass() {
		return $this->name. 'Entity';
	}
	
	protected function entityClassForData($data) {
		return $this->entityClass();
	}
	
	public function allEntities($params = array()) {
		$params['entity'] = true;
		return $this->find('all', $params);
	}
	
	public function entities($params = array()) {
		return $this->allEntities($params);
	}
	
	public function call__($method, $params) {
		list($to_entity, $method) = $this->analyzeMethodName($method);
		
		$return = parent::call__($method, $params);
		
		if ($to_entity and !is_null($return)) {
			$return = $this->convertToEntities($return);
		}
		
		return $return;
	}
	
	protected function analyzeMethodName($method) {
		$to_entity = false;
		
		if (preg_match('/^(entity|(?:all)?entities)by(.+)$/i', $method, $matches)) {
			$to_entity = true;
			$all = (strtolower($matches[1]) != 'entity');
			$method = ($all ? 'findAllBy' : 'findBy'). $matches[2];
		}
		
		return array($to_entity, $method);
	}
	
	/**
	 * Override. To support passing entity to set() directly.
	 * Because save() will pass its data to set(), you can now
	 * call save() with entity like this:
	 *
	 *    $Model->save($entity);
	 *
	 */
	public function set($one, $two = null) {
		if (is_a($one, 'Entity')) {
			$one = $one->toArray();
		}
		return parent::set($one, $two);
	}
	
	public function paginateCount($conditions, $recursive, $extra) {
		$parameters = $extra + compact('conditions');
		if ($recursive != $this->recursive) {
			$parameters['recursive'] = $recursive;
		}
		$parameters['entity'] = false;
		
		return $this->find('count', $parameters);
	}
	
	public function paginate($conditions, $fields, $order, $limit, $page, $recursive, $extra) {
		$params = compact('conditions', 'fields', 'order', 'limit', 'page');
		
		if ($recursive != $this->recursive) {
			$params['recursive'] = $recursive;
		}
		
		$type = !empty($extra['type']) ? $extra['type'] : 'all';
		
		return $this->find($type, array_merge($params, $extra));
	}
	
	public function count($conditions = null) {
		return $this->find('count', array(
			'conditions' => $conditions, 
			'recursive' => -1
		));
	}
	
	public function assignAttribute(Entity $entity, $alias, $value) {
		$name = Inflector::underscore($alias);
		
		$Model = $this->getAssociatedModel($alias);
		if ($Model) {
			if (Set::numeric(array_keys($value))) {
				$result = array();
				foreach ($value as $columns) {
					$data = array($alias => $columns);
					$result[] = $Model->entity($data);
				}
				$name = Inflector::pluralize($name);
				$value = $result;
			} else {
				$data = array($alias => $value);
				$value = $Model->entity($data);
			}
		}
		
		$entity->{$name} = $value;
	}
	
	public function getAssociatedModel($alias) {
		if ($this->schema($alias) or !preg_match('/^[A-Z]/', $alias)) {
			return null;
		}
		
		$Model = null;
		
		foreach ($this->__associations as $type) {
			if (!empty($this->{$type}[$alias])) {
				$association = $this->{$type}[$alias];
				
				$Model = ClassRegistry::init(array(
					'class' => $association['className'], 
					'alias' => $alias, 
				));
				
				break;
			}
		}
		
		if (!$Model) {
			$Model = ClassRegistry::init($alias);
		}
		
		if ($Model and is_a($Model, 'EntityModel')) {
			return $Model;
		}
		
		return null;
	}
}

