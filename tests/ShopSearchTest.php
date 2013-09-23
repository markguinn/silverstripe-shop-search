<?php
/**
 * Basic tests for searching (uses the MysqlSimple adapter)
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.23.2013
 * @package shop_search
 * @subpackage tests
 */
class ShopSearchTest extends SapphireTest
{
	static $fixture_file = 'ShopSearchTest.yml';

	function setUpOnce() {
		Config::inst()->update('ShopSearch', 'adapter_class', 'ShopSearchMysqlSimple');
		Config::inst()->remove('Product', 'searchable_fields');
		Config::inst()->update('Product', 'searchable_fields', array('Title', 'Content'));
		parent::setUpOnce();
	}

	function testResults() {
		// Searching for nothing should return all results
		$r = ShopSearch::inst()->search(array());
		$this->assertNotNull($r);
		$this->assertInstanceOf('ArrayData', $r);
		$allProds = Product::get()->count();
		$this->assertEquals($allProds, $r->TotalMatches);
		$this->assertEquals($allProds, $r->Matches->count());

		// Searching for something random should return no results
		$r = ShopSearch::inst()->search(array('q' => 'THISshouldNEVERbePRESENT'));
		$this->assertEquals(0, $r->TotalMatches);
		$this->assertEquals(0, $r->Matches->count());

		// Searching for 'Green' should return two results (one from the title and one from the content)
		$r = ShopSearch::inst()->search(array('q' => 'green'));
		$this->assertEquals(2, $r->TotalMatches);
		$this->assertEquals(2, $r->Matches->count());
		$this->assertDOSContains(array(
			array('Title'=>'Big Book of Funny Stuff'),
			array('Title'=>'Green Pickles'),
		), $r->Matches);

		// TODO: search with filters
		// TODO: paging
	}

	function testLogging() {
		/** @var Member $m1 */
		$m1 = $this->objFromFixture('Member', 'm1');
		$m1->logOut();
		$this->assertEquals(0, SearchLog::get()->count());

		// Searching for nothing should not leave a record
		ShopSearch::inst()->search(array());
		$this->assertEquals(0, SearchLog::get()->count());

		// Searching should leave a log record
		ShopSearch::inst()->search(array('q' => 'green'));
		$this->assertEquals(1, SearchLog::get()->count());
		$log = SearchLog::get()->last();
		$this->assertEquals('green', $log->Query);
		$this->assertEquals(2, $log->NumResults);
		$this->assertEquals(0, $log->MemberID);

		// If we log in as a customer, the search log should register that
		$m1->logIn();
		ShopSearch::inst()->search(array('q' => 'purple'));
		$this->assertEquals(2, SearchLog::get()->count());
		$log = SearchLog::get()->last();
		$this->assertEquals('purple', $log->Query);
		$this->assertEquals(1, $log->NumResults);
		$this->assertEquals($m1->ID, $log->MemberID);
		$m1->logOut();
	}

	function testSuggestions() {
		// Initially should not suggest anything
		$r = ShopSearch::inst()->suggest();
		$this->assertEquals(0, count($r));

		// After a few searches, general search should give top 10 by popularity
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'really not found'));
		ShopSearch::inst()->search(array('q' => 'green'));
		ShopSearch::inst()->search(array('q' => 'GReen'));
		ShopSearch::inst()->search(array('q' => 'brown'));
		ShopSearch::inst()->search(array('q' => 'Red'));
		ShopSearch::inst()->search(array('q' => 'rEd'));
		ShopSearch::inst()->search(array('q' => 'reD'));
		$r = ShopSearch::inst()->suggest();
		$this->assertEquals(4, count($r));
		$this->assertEquals('red', $r[0]);
		// this should be later in the listing, even though it was searched for more often because it didn't return any results
		$this->assertEquals('really not found', $r[2]);
		$this->assertEquals('brown', $r[3]);

		// Search for a specific string should limit suggestions
		$r = ShopSearch::inst()->suggest('re');
		$this->assertEquals(3, count($r));
		$this->assertEquals('red', $r[0]);
		$this->assertEquals('green', $r[1]);
	}
}
