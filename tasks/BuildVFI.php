<?php
/**
 * Rebuilds all VirtualFieldIndexes
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 9.26.13
 * @package shop_search
 * @subpackage tasks
 */
class BuildVFI extends BuildTask
{
	protected $title = 'Rebuild Virtual Field Indexes';
	protected $description = 'Rebuild all VFI fields on all tables and records.';

	function run($request) {
		$classes = VirtualFieldIndex::get_classes_with_vfi();

		// rebuild the indexes
		foreach ($classes as $c) {
			echo "Rebuilding $c...";
			VirtualFieldIndex::build();
			echo "Republishing changed records...";
			foreach (DataObject::get($c) as $rec) {
				if ($rec->isPublished()) {
					$rec->publish('Stage', 'Live');
					$rec->flushCache();
				}
			}
			echo "<br>\n";
		}

		echo 'Task complete.';
	}
}
