<?php
/**
 * If the queued jobs module is installed, this will be used instead of
 * updating vfi's in onBeforeWrite.
 *
 * @author  Mark Guinn <mark@adaircreative.com>
 * @date    07.02.2015
 * @package shop_search
 * @subpackage helpers
 */
if(!interface_exists('QueuedJob')) return;

class VirtualFieldIndexQueuedJob extends AbstractQueuedJob implements QueuedJob
{
	/**
	 * The QueuedJob queue to use when processing updates
	 * @config
	 * @var int
	 */
	private static $reindex_queue = 2; // QueuedJob::QUEUED;


	/**
	 * @param DataObject $object
	 * @param array $fields
	 */
	public function __construct($object, array $fields) {
		$this->setObject($object);
		$this->rebuildFields = $fields;
	}


	/**
	 * Helper method
	 */
	public function triggerProcessing() {
		singleton('QueuedJobService')->queueJob($this);
	}


	/**
	 * @return string
	 */
	public function getTitle() {
		$obj = $this->getObject();
		return "Update Virtual Field Indexes: " . ($obj ? $obj->getTitle() : '???');
	}


	/**
	 * Reprocess any needed fields
	 */
	public function process() {
		Versioned::reading_stage('Stage');
		$obj = $this->getObject();

		if ($obj) {
			$obj->rebuildVFI();
		}

		$this->isComplete = true;
	}
}

