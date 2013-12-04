<?php
/**
 * Adds a new field to the mysql table which can hold the result
 * of a "virtual field" (i.e. method call on the model or relation).
 *
 * Usage (config.yml):
 *
 * Product:
 *   extensions:
 *     - VirtualFieldIndex
 * VirtualFieldIndex
 *   vfi_spec:
 *     Product:
 *       Price:
 *         Type: simple
 *         Source: sellingPrice
 *         DependsOn: BasePrice
 *         DBField: Currency
 *       Categories:
 *         Type: list
 *         DependsOn: all
 *         Source:
 *           - ParentID
 *           - ProductCategories.ID
 *
 * The above will create two new fields on Product: VFI_Price and VFI_Categories.
 * These will be updated whenever the object is changed and can be triggered via
 * a build task (dev/tasks/BuildVFI).
 *
 * The categories index will contain the merging of results from ParentID and
 * ProductCategories.ID in the form of a comma-delimited list.
 *
 * NOTE: having multiple sources doesn't equate with Type=list always. That's
 * just the default. Type=list means the output is a list. A single source could
 * also return an array and that would be a list as well.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.25.2013
 * @package shop_search
 * @subpackage helpers
 */
class VirtualFieldIndex extends DataExtension
{
	const TYPE_LIST     = 'list';
	const TYPE_SIMPLE   = 'simple';
	const DEPENDS_ALL   = 'all';
	const DEPENDS_NONE  = 'none';

	/** @var array - central config for all models */
	private static $vfi_spec = array();

	/** @var bool - used to prevent an infinite loop in onBeforeWrite */
	protected $isRebuilding = false;

	public static $disable_building = false;

    /**
     * @return array
     */
    public static function get_classes_with_vfi() {
        $vfi_def = Config::inst()->get('VirtualFieldIndex', 'vfi_spec');
        if (!$vfi_def || !is_array($vfi_def)) return array();
        return array_keys($vfi_def);
    }

	/**
	 * Define extra db fields and indexes.
	 * @param $class
	 * @param $extension
	 * @param $args
	 * @return array
	 */
	public static function get_extra_config($class, $extension, $args) {
		$vfi_def = self::get_vfi_spec($class);
		if (!$vfi_def || !is_array($vfi_def)) return array();

		$out = array(
			'db'        => array(),
			'indexes'   => array(),
		);

		foreach ($vfi_def as $field => $spec) {
			$fn = 'VFI_' . $field;
			$out['db'][$fn] = isset($spec['DBField']) ? $spec['DBField'] : 'Varchar(255)';
			$out['indexes'][$fn] = true;
		}

		return $out;
	}


	/**
	 * Return a normalized version of the vfi definition for a given class
	 * @param string $class
	 * @return array
	 */
	public static function get_vfi_spec($class) {
		$vfi_master = Config::inst()->get('VirtualFieldIndex', 'vfi_spec');
		if (!$vfi_master || !is_array($vfi_master)) return array();

		// merge in all the vfi's from ancestors as well
		$vfi_def = array();
		foreach (ClassInfo::ancestry($class) as $c) {
			if (!empty($vfi_master[$c])) {
				// we want newer classes to override parent classes so we do it this way
				$vfi_def = $vfi_master[$c] + $vfi_def;
			}
		}
		if (empty($vfi_def)) return array();

		// convert shorthand to longhand
		foreach ($vfi_def as $k => $v) {
			if (is_numeric($k)) {
				$vfi_def[$v] = $v;
				unset($vfi_def[$k]);
			} elseif (is_string($v)) {
				$vfi_def[$k] = array(
					'Type'      => self::TYPE_SIMPLE,
					'DependsOn' => self::DEPENDS_ALL,
					'Source'    => $v,
				);
			} elseif (is_array($v) && !isset($vfi_def[$k]['Source'])) {
				$vfi_def[$k] = array(
					'Type'      => self::TYPE_LIST,
					'DependsOn' => self::DEPENDS_ALL,
					'Source'    => $v,
				);
			} else {
				if (!isset($v['Type'])) $vfi_def[$k]['Type'] = is_array($v['Source']) ? self::TYPE_LIST : self::TYPE_SIMPLE;
				if (!isset($v['DependsOn'])) $vfi_def[$k]['DependsOn'] = self::DEPENDS_ALL;
			}
		}

		return $vfi_def;
	}

	/**
	 * Rebuilds any vfi fields on one class (or all). Doing it in chunks means a few more
	 * queries but it means we can handle larger datasets without storing everything in memory.
	 *
	 * @param string $class [optional] - if not given all indexes will be rebuilt
	 */
	public static function build($class='') {
		if ($class) {
			$list   = DataObject::get($class);
			$count  = $list->count();
			for ($i = 0; $i < $count; $i += 10) {
				$chunk = $list->limit(10, $i);
//				if (Controller::curr() instanceof TaskRunner) echo "Processing VFI #$i...\n";
				foreach ($chunk as $rec) $rec->rebuildVFI();
			}
		} else {
			foreach (self::get_classes_with_vfi() as $c) self::build($c);
		}
	}

	/**
	 * Rebuild all vfi fields.
	 */
	public function rebuildVFI($field = '') {
		if ($field) {
			$this->isRebuilding = true;
			$spec   = $this->getVFISpec($field);
			$fn     = $this->getVFIFieldName($field);
			$val    = $this->getVFI($field, true);

			if ($spec['Type'] == self::TYPE_LIST) {
				if (is_object($val)) $val = $val->toArray();    // this would be an ArrayList or DataList
				if (!is_array($val)) $val = array($val);        // this would be a scalar value
				$val = self::encode_list($val);
			} else {
				if (is_array($val))  $val = (string)$val[0];    // if they give us an array, just take the first value
				if (is_object($val)) $val = (string)$val->first();  // if a SS_List, take the first as well
			}

			$this->owner->setField($fn, $val);
			$this->owner->write();
			$this->isRebuilding = false;
		} else {
			// rebuild all fields if they didn't specify
			foreach ($this->getVFISpec() as $field => $spec) {
				$this->rebuildVFI($field);
			}
		}
	}

	/**
	 * @param $name
	 * @return string
	 */
	public function getVFIFieldName($name) {
		return 'VFI_' . $name;
	}


	/**
	 * @param string $field [optional]
	 * @return array|false
	 */
	public function getVFISpec($field = '') {
		$spec = self::get_vfi_spec($this->owner->class);
		if ($field) {
			return empty($spec[$field]) ? false : $spec[$field];
		} else {
			return $spec;
		}
	}


	/**
	 * @param string $field
	 * @param bool   $fromSource [optional] - if true, it will regenerate the data from the source fields
	 * @param bool   $forceIDs [optional] - if true, it will return an ID even if the norm is to return a DataObject
	 * @return string|array|SS_List
	 */
	public function getVFI($field, $fromSource=false, $forceIDs=false) {
		$spec = $this->getVFISpec($field);
		if (!$spec) return null;
		if ($fromSource) {
			if (is_array($spec['Source'])) {
				$out = array();
				foreach ($spec['Source'] as $src) {
					$myOut = self::get_value($src, $this->owner);
					if (is_array($myOut)) {
						$out = array_merge($out, $myOut);
					} elseif (is_object($myOut) && $myOut instanceof SS_List) {
						$out = array_merge($out, $myOut->toArray());
					} else {
						$out[] = $myOut;
					}
				}
				return $out;
			} else {
				return self::get_value($spec['Source'], $this->owner);
			}
		} else {
			$val = $this->owner->getField($this->getVFIFieldName($field));
			if ($spec['Type'] == self::TYPE_LIST) {
				return self::decode_list($val, $forceIDs);
			} else {
				return $val;
			}
		}
	}


	/**
	 * Template version
	 * @param string $field
	 * @return string|array|SS_List
	 */
	public function VFI($field) {
		return $this->getVFI($field);
	}


	/**
	 * @param array $list
	 * @return string
	 */
	protected static function encode_list(array $list) {
		// If we've got objects, encode them a little differently
		if (count($list) > 0 && is_object($list[0])) {
			$ids = array();
			foreach ($list as $rec) $ids[] = $rec->ID;
			$val = '>' . $list[0]->ClassName . '|' . implode('|', $ids) . '|';
		} else {
			$val = '|' . implode('|', $list) . '|';
		}

		return $val;
	}


	/**
	 * @param string $val
	 * @param bool   $forceIDs [optional] - if true encoded objects will not be returned as objects but as id's
	 * @return array
	 */
	protected static function decode_list($val, $forceIDs=false) {
		if ($val[0] == '>') {
			$firstBar = strpos($val, '|');
			if ($firstBar < 3) return array();
			$className = substr($val, 1, $firstBar-1);
			$ids = explode('|', trim(substr($val, $firstBar), '|'));
			return $forceIDs ? $ids : DataObject::get($className)->filter('ID', $ids)->toArray();
		} else {
			return explode('|', trim($val, '|'));
		}
	}


	/**
	 * This is largely borrowed from DataObject::relField, but
	 * adapted to work with many-many and has-many fields.
	 * @param string $fieldName
	 * @param DataObject $rec
	 * @return mixed
	 */
	protected static function get_value($fieldName, DataObject $rec) {
		$component = $rec;

		// We're dealing with relations here so we traverse the dot syntax
		if (strpos($fieldName, '.') !== false) {
			$relations = explode('.', $fieldName);
			$fieldName = array_pop($relations);
			foreach ($relations as $relation) {
				// Inspect $component for element $relation
				if ($component->hasMethod($relation)) {
					// Check nested method
					$component = $component->$relation();
				} elseif ($component instanceof SS_List) {
					// Select adjacent relation from DataList
					$component = $component->relation($relation);
				} elseif ($component instanceof DataObject && ($dbObject = $component->dbObject($relation))) {
					// Select db object
					$component = $dbObject;
				} else {
					user_error("$relation is not a relation/field on ".get_class($component), E_USER_ERROR);
				}
			}
		}

		// Bail if the component is null
		if (!$component) {
			return null;
		} elseif ($component instanceof SS_List) {
			return $component->column($fieldName);
		} elseif ($component->hasMethod($fieldName)) {
			return $component->$fieldName();
		} else {
			return $component->$fieldName;
		}
	}

	/**
	 * Trigger rebuild if needed
	 */
	public function onBeforeWrite() {
		if ($this->isRebuilding || self::$disable_building) return;
		foreach ($this->getVFISpec() as $field => $spec) {
			$rebuild = false;

			if ($spec['DependsOn'] == self::DEPENDS_NONE) {
				continue;
			} elseif ($spec['DependsOn'] == self::DEPENDS_ALL) {
				$rebuild = true;
			} elseif (is_array($spec['DependsOn'])) {
				foreach ($spec['DependsOn'] as $f) {
					if ($this->owner->isChanged($f)) {
						$rebuild = true;
						break;
					}
				}
			} else {
				if ($this->owner->isChanged($spec['DependsOn'])) {
					$rebuild = true;
				}
			}

			if ($rebuild) $this->rebuildVFI($field);
		}
	}
}