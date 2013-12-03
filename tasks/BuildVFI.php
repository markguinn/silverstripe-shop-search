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
			VirtualFieldIndex::build($c);

//			echo "Republishing changed records...";
//			$list   = DataObject::get($c);
//			$count  = $list->count();
//			for ($i = 0; $i < $count; $i += 10) {
//				$chunk = $list->limit(10, $i);
//				foreach ($chunk as $rec) {
//					if ($rec->isPublished()) {
//						$rec->publish('Stage', 'Live');
//						$rec->flushCache();
//					}
//				}
//			}

			echo "<br>\n";
		}

		echo "Task complete.\n\n";
	}
}
