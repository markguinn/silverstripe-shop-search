<?php
/**
 * Extension for Product that allows static attributes in the same way
 * they're used for variations. I'm separating this out because it will
 * probably be useful in other projects.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 01.21.2014
 * @package Daywind
 * @subpackage extensions
 */
class HasStaticAttributes extends DataExtension
{
	private static $many_many = array(
		'StaticAttributeTypes'  => 'ProductAttributeType',
		'StaticAttributeValues' => 'ProductAttributeValue',
	);


	/**
	 * Adds variations specific fields to the CMS.
	 */
	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldsToTab('Root.Attributes', array(
			HeaderField::create('Applicable Attribute Types'),
			CheckboxSetField::create('StaticAttributeTypes', 'Static Attribute Types', ProductAttributeType::get()->map("ID", "Title")),
			LiteralField::create('staticattributehelp', '<p>Select any attributes that apply to this product and click Save.</p>'),
			HeaderField::create('Attributes'),
		));

		foreach ($this->owner->StaticAttributeTypes() as $type) {
			$source = $this->getValuesClosure($type->ID);

			$newValFields = FieldList::create(array(
				TextField::create('Value', 'Label'),
				HiddenField::create('TypeID', '', $type->ID),
			));

			$newValReq = RequiredFields::create('Value');

			$valuesField = HasStaticAttributes_CheckboxSetField::create('StaticAttributeValues-'.$type->ID, $type->Title, $source());
			$valuesField->setValue( $this->owner->StaticAttributeValues()->filter('TypeID', $type->ID)->getIDList() );
			$valuesField->useAddNew('ProductAttributeValue', $source, $newValFields, $newValReq);

			$fields->addFieldToTab('Root.Attributes', $valuesField);
		}
	}


	/**
	 * @param $typeID
	 * @return callable
	 */
	protected function getValuesClosure($typeID) {
		return function() use ($typeID) {
			return ProductAttributeValue::get()->filter('TypeID', $typeID)->map('ID', 'Value')->toArray();
		};
	}


	/**
	 * @return ArrayList
	 */
	public function StaticAttributes() {
		$list = array();

		foreach ($this->owner->StaticAttributeTypes() as $type) {
			$type->ActiveValues = new ArrayList();
			$list[$type->ID] = $type;
		}

		foreach ($this->owner->StaticAttributeValues() as $val) {
			if (!isset($list[$val->TypeID])) continue;
			$list[$val->TypeID]->ActiveValues->push($val);
		}

		return new ArrayList($list);
	}


	/**
	 * Add the default attribute types if any
	 */
	public function onBeforeWrite() {
		if (empty($this->owner->ID)) {
			$defaultAttributes = Config::inst()->get($this->owner->ClassName, 'default_attributes');
			if (!empty($defaultAttributes)) {
				$types = $this->owner->StaticAttributeTypes();
				foreach ($defaultAttributes as $typeID) $types->add($typeID);
			}
		}
	}

}


/**
 * This is needed because the checkboxsetfield doesn't have an easy
 * way to segment by TypeID. This is exactly the same except that
 * you set the name to StaticAttributeValues-<TypeID>.
 *
 * @class HasStaticAttributes_CheckboxSetField
 */
class HasStaticAttributes_CheckboxSetField extends CheckboxSetField
{
	/**
	 * @return int
	 */
	public function getAttributeTypeID() {
		$parts = explode('-', $this->name);
		return count($parts) > 1 ? $parts[1] : 0;
	}


	/**
	 * @return string
	 */
	public function getFieldName() {
		$parts = explode('-', $this->name);
		return count($parts) > 0 ? $parts[0] : '';
	}


	/**
	 * Save the current value of this CheckboxSetField into a DataObject.
	 * If the field it is saving to is a has_many or many_many relationship,
	 * it is saved by setByIDList(), otherwise it creates a comma separated
	 * list for a standard DB text/varchar field.
	 *
	 * @param DataObjectInterface $record The record to save into
	 */
	public function saveInto(DataObjectInterface $record) {
		$fieldname  = $this->getFieldName();
		if (empty($fieldname)) return;
		$typeID     = $this->getAttributeTypeID();
		if (empty($typeID)) return;
		$relation   = $record->$fieldname();
		if (!$relation) return;
		$relation   = $relation->filter('TypeID', $typeID);

		// make a list of id's that should be there
		$idList = array();
		if (!empty($this->value) && is_array($this->value)) {
			foreach($this->value as $id => $bool) {
				if($bool) {
					$idList[$id] = $id;
				}
			}
		}

		// look at the existing elements and add/subtract
		$toDelete = array();
		foreach ($relation as $rec) {
			if (isset($idList[$rec->ID])) {
				// don't try to add it twice
				unset($idList[$rec->ID]);
			} else {
				$toDelete[] = $rec->ID;
			}
		}

		// add
		foreach ($idList as $id) $relation->add($id);

		// remove
		foreach ($toDelete as $id) $relation->removeByID($id);
	}


	/**
	 * Load a value into this CheckboxSetField
	 */
	public function setValue($value, $obj = null) {
		if (!empty($value) && !is_array($value) && !empty($this->value)) {
			$this->value[] = $value;
		} else {
			parent::setValue($value, $obj);
		}

		return $this;
	}

}


//class HasStaticAttributes_ProductAttributeType extends DataExtension
//{
//
//}
//
//class HasStaticAttributes_ProductAttributeValue extends DataExtension
//{
//	public function getAddNewFields() {
//		return new FieldList(array(
//
//		));
//	}
//}
