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
		// TODO: may need to normalize a bit more config here
		Config::inst()->update('ShopSearch', 'adapter_class', 'ShopSearchMysqlSimple');
		Config::inst()->remove('Product', 'searchable_fields');
		Config::inst()->update('Product', 'searchable_fields', array('Title', 'Content'));
		Config::inst()->remove('ShopSearch', 'facets');
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

	/**
	 * Sorry, this one will be messy if you add new products to the fixture
	 */
	function testFacets() {
		$s = ShopSearch::inst();
		Config::inst()->update('ShopSearch', 'facets', array(
			'Model'     => 'By Model',
		));

		// Given a search for nothing with 1 facet............................................
		$r = $s->search(array('q' => ''));
		$this->assertEquals(4, $r->TotalMatches,        'Should contain all products');
		$this->assertNotEmpty($r->Facets,               'Facets should be present');
		$this->assertEquals(1, $r->Facets->count(),     'There should be one facet');
		$model = $r->Facets->first();
		$this->assertEquals('By Model', $model->Label,  'Label should be correct');
		$this->assertEquals(3, $model->Values->count(), 'Should be 3 values');
		$model1 = $model->Values->first();
		$this->assertEquals('ABC', $model1->Label,      'Value label should be correct');
		$this->assertEquals(2, $model1->Count,          'Value count should be correct');

		// Given a search for 'green' with 1 facet............................................
		$r = $s->search(array('q' => 'green'));
		$this->assertEquals(2, $r->TotalMatches,        'Should contain 2 products');
		$this->assertNotEmpty($r->Facets,               'Facets should be present');
		$this->assertEquals(1, $r->Facets->count(),     'There should be one facet');
		$model = $r->Facets->first();
		$this->assertEquals('By Model', $model->Label,  'Label should be correct');
		$this->assertEquals(2, $model->Values->count(), 'Should be 3 values');
		$model1 = $model->Values->first();
		$this->assertEquals('ABC', $model1->Label,      'Value label should be correct');
		$this->assertEquals(1, $model1->Count,          'Value count should be correct');

		// Given a search with price and category facets......................................
		Config::inst()->update('ShopSearch', 'facets', array(
			'Model'     => 'By Model',
			'Price'     => 'By Price',
			'Parent,ProductCategories' => 'By Category', // This will facet across two fields
		));

		$r = $s->search(array('q' => ''));
		$this->assertEquals(3, $r->Facets->count(),     'There should be 3 facets');
		$price = $r->Facets->offsetGet(1);
		$this->assertEquals(3, $price->Values->count(), 'There should be 3 prices');
		$p1 = $price->Values->first();
		$this->assertEquals('$5.00', $p1->Label,        'Price label should be formatted');
		$cat = $r->Facets->last();
		$this->assertEquals(3, $cat->Values->count(),   'There should be 3 categories');
		$c1 = $cat->Values->first();
		$c3 = $cat->Values->last();
		$this->assertEquals('Farm Stuff', $c1->Label,   'Category label should work');
		$this->assertEquals(2, $c1->Count,              'Category count should work');
		$this->assertEquals(3, $c3->Count,              'Category counts should include the secondary many/many relation');
	}
}
