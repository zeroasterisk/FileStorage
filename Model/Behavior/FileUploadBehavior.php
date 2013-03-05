<?php
App::uses('ModelBehavior', 'Model');
/**
 * File Upload Behavior
 *
 * This behavior will store uploaded files in a hasMany or hasOne association
 * it builds on the fly to the FileStorage model.
 *
 * The files itself will be stored in the configured storage backend.
 *
 * @author Florian Krämer
 * @copyright 2012 Florian Krämer
 * @license MIT
 */
class FileUploadBehavior extends ModelBehavior {

/**
 * Default settings
 *
 * @var array
 */
	protected $_defaults = array(
		'fileField' => 'file',
		'pathField' => 'path',
		'adapterConfig' => 'Local',
		'storageCallback' => 'afterSave',
		'storageDeleteCallback' => 'afterDelete',
		'storageModelAssociation' => true,
		'storageModelAssocName' => '',
		'storageKeyCallback' => 'computeStorageKey');

/**
 * Behavior Setup
 *
 * @param Model $Model
 * @param array $settings
 * @throws InvalidArgumentException
 * @return void
 */
	public function setup(Model $Model, $settings = array()) {
		if (!is_array($settings)) {
			throw new InvalidArgumentException(__d('file_storage', 'Settings must be passed as array!'));
		}

		$this->settings[$Model->alias] = array_merge($this->_defaults, $settings);
		extract($this->settings[$Model->alias]);

		if ($storageModelAssociation === true) {
			$Model->bindModel(array(
				'hasMany' => array(
					'File' => array(
						'className' => 'FileStorage.FileStorage',
						'foreignKey' => 'foreign_key',
						'conditions' => array('File.model' => $Model->name),
						'depends' => false))));
			$this->settings[$Model->alias]['storageModelAssocName'] = 'File';
		} elseif(is_array($storageModelAssociation)) {
			$Model->bindModel($storageModelAssociation);
			if (isset($storageModelAssociation['hasOne'])) {
				$keys = array_keys($storageModelAssociation['hasOne']);
				$assocKeys[0];
			}
			if (isset($storageModelAssociation['hasMany'])) {
				$keys = array_keys($storageModelAssociation['hasMany']);
				$assocKeys[0];
			}
			if (isset($key)) {
				$this->settings[$Model->alias]['storageModelAssocName'] = $assocKeys[0];
			}
		} elseif (is_string($storageModelAssociation)) {
			$this->settings[$Model->alias]['storageModelAssocName'] = $storageModelAssociation;
		}
	}

/**
 * Saves an uploaded file
 *
 * @param Model $Model
 * @return boolean
 */
	public function saveUploadedFile(Model $Model) {
		extract($this->settings[$Model->alias]);
		if (method_exists($Model, $storageKeyCallback)) {
			$key = $Model->{$storageKeyCallback}();
		} else {
			$key = $this->computeKey();
		}

		if (StorageManager::adapter($adapterConfig)->write($key, file_get_contents($Model->data[$storageModelAssocName][$fileField]['tmp_name']))) {
			$Model->data[$storageModelAssocName]['model'] = get_class($Model);
			$Model->data[$storageModelAssocName]['foreign_key'] = $Model->getLastInsertId();
			$Model->data[$storageModelAssocName][$pathField] = $key;
			return true;
		}

		return false;
	}

/**
 * beforeSave callback
 *
 * @param Model $Model
 * @return boolean
 */
	public function beforeSave(Model $Model) {
		extract($this->settings[$Model->alias]);
		if ($storageCallback === 'beforeSave') {
			return $this->saveuploadedFile($Model);
		}
		return true;
	}

/**
 * afterSave
 *
 * @param Model $Model
 * @param boolean $created
 * @return boolean
 */
	public function afterSave(Model $Model, $created) {
		extract($this->settings[$Model->alias]);
		if ($storageCallback === 'afterSave') {
			return $this->saveuploadedFile($Model);
		}
		return true;
	}

/**
 * afterSave
 *
 * @param Model $Model
 * @return boolean
 */
	public function beforeDelete(Model $Model) {
		extract($this->settings[$Model->alias]);
		if ($storageDeleteCallback == 'beforeDelete') {
			return $this->deleteFiles($Model);
		}
	}


/**
 * afterDelete
 *
 * @param Model $Model
 * @param boolean $cascade
 * @return boolean
 */
	public function afterDelete(Model $Model, $cascade = true) {
		extract($this->settings[$Model->alias]);

		if ($storageDeleteCallback == 'afterDelete') {
			$this->deleteFiles($Model);
		}
	}

/**
 * deleteFiles
 *
 * @param Model $Model
 * @return boolean
 */
	protected function deleteFiles(Model $Model) {
		$files = $Model->{$storageModelAssocName}->find('all', array(
			'contain' => array(),
			'conditions' => array(
				$storageModelAssocName . '.model' => get_class($Model),
				$storageModelAssocName . '.foreign_key' => $Model->id)));

		$result = true;
		if (!empty($files)) {
			if (in_array($storageModelAssocName, $Model->hasMany)) {
				foreach ($files as $file) {
					if (!$this->deleteFile($file)) {
						$result = false;
					}
				}
			}
			if (in_array($storageModelAssocName, $Model->hasOne)) {
				if (!$this->deleteFile($files[$storageModelAssocName])) {
					$result = false;
				}
			}
		}
		return $result;
	}

/**
 * computeKey
 *
 * @return string
 */
	public function computeKey() {
		return String::uuid();
	}

/**
 * Deletes a file from a storage backend
 *
 * @param array
 * @return boolean
 */
	public function deleteFile($data) {
		if (StorageManager::adapter($data['adapter'])->delete($data['path'])) {
			return true;
		}
		return false;
	}

}