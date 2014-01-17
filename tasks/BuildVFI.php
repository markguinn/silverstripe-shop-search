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

	static $recordsPerRequest = 5000;

	function old_run($request) {
		$classes = VirtualFieldIndex::get_classes_with_vfi();
		ini_set('memory_limit', '1G');
		$start = (int)$request->requestVar('start');
		$n = $start;

		// rebuild the indexes
		foreach ($classes as $c) {
			echo "Rebuilding $c...";
			$list   = DataObject::get($c);
			$count  = $list->count();
			for ($i = $n; $i < $count; $i += 10) {
				$chunk = $list->limit(10, $i);
				if (Controller::curr() instanceof TaskRunner) echo "Processing VFI #$i...\n";
				foreach ($chunk as $rec) $rec->rebuildVFI();
			}
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

	function run($request) {
		increase_time_limit_to();
		$self = get_class($this);
		$verbose = isset($_GET['verbose']);

		if (isset($_GET['start'])) {
			$this->runFrom($_GET['class'], $_GET['start']);
		}
		else {
			foreach(array('framework','sapphire') as $dirname) {
				$script = sprintf("%s%s$dirname%scli-script.php", BASE_PATH, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR);
				if(file_exists($script)) {
					break;
				}
			}

			$classes = VirtualFieldIndex::get_classes_with_vfi();
			foreach ($classes as $class) {
				if (isset($_GET['class']) && $class != $_GET['class']) continue;
				$singleton = singleton($class);
				$query = $singleton->get($class);
				$dtaQuery = $query->dataQuery();
				$sqlQuery = $dtaQuery->getFinalisedQuery();
				$singleton->extend('augmentSQL',$sqlQuery,$dtaQuery);
				$total = $query->count();
				$startFrom = isset($_GET['startfrom']) ? $_GET['startfrom'] : 0;

				echo "Class: $class, total: $total\n\n";

				for ($offset = $startFrom; $offset < $total; $offset += $this->stat('recordsPerRequest')) {
					echo "$offset..";
					$cmd = "php $script dev/tasks/$self class=$class start=$offset";
					if($verbose) echo "\n  Running '$cmd'\n";
					$res = $verbose ? passthru($cmd) : `$cmd`;
					if($verbose) echo "  ".preg_replace('/\r\n|\n/', '$0  ', $res)."\n";
				}
			}
		}
	}

	protected function runFrom($class, $start) {
		$items = DataList::create($class)->limit($this->stat('recordsPerRequest'), $start);
		foreach ($items as $item) {
			$item->rebuildVFI();
		}
	}

}
