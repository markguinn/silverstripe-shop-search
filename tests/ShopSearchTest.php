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
    public static $fixture_file = 'ShopSearchTest.yml';

    public function setUpOnce()
    {
        // normalize the configuration
        Config::inst()->update('ShopSearch', 'buyables_are_searchable', false);
        Config::inst()->remove('ShopSearch', 'searchable');
        Config::inst()->update('ShopSearch', 'searchable', array('Product'));
        Config::inst()->update('ShopSearch', 'adapter_class', 'ShopSearchSimple');
        Config::inst()->remove('Product', 'searchable_fields');
        Config::inst()->update('Product', 'searchable_fields', array('Title', 'Content'));
        Config::inst()->remove('Product', 'default_attributes');
        Config::inst()->remove('ShopSearch', 'facets');
        Config::inst()->update('FacetHelper', 'sort_facet_values', true);
        Config::inst()->update('FacetHelper', 'faster_faceting', false);

        $p = singleton('Product');
        if (!$p->hasExtension('VirtualFieldIndex')) {
            Product::add_extension('VirtualFieldIndex');
        }

        Config::inst()->remove('VirtualFieldIndex', 'vfi_spec');
        Config::inst()->update('VirtualFieldIndex', 'vfi_spec', array(
            'Product' => array(
                'Price2'    => 'sellingPrice',
                'Price'     => array('Source' => 'sellingPrice', 'DBField' => 'Currency', 'DependsOn' => 'BasePrice'),
                'Category'  => array('Parent', 'ProductCategories'),
            ),
        ));

        if (!$p->hasExtension('HasStaticAttributes')) {
            Product::add_extension('HasStaticAttributes');
        }

        parent::setUpOnce();
    }


    public function testResults()
    {
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
    }


    public function testLogging()
    {
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

        // Refining a search several times should leave us a crumb trail
        $s = ShopSearch::inst();
        $r = $s->search(array('q' => 'green'));
        $this->assertNotNull($r->SearchBreadcrumbs,                                 'Search crumb exists');
        $this->assertEquals(1, $r->SearchBreadcrumbs->count(),                      'Search crumb should have 1 entry');
        $this->assertEquals('Search: green', $r->SearchBreadcrumbs->first()->Title, 'Search crumb label should be correct');

        $r = $s->search(array(
            'q'     => 'green',
            '__ps'  => $r->SearchLogID,
            '__t'   => 'Model: ABC',
            'f' => array(
                'Model' => 'ABC',
            ),
        ));
        $this->assertEquals(2, $r->SearchBreadcrumbs->count(),                      'Search crumb should have 2 entries');
        $this->assertEquals('Search: green', $r->SearchBreadcrumbs->first()->Title, 'Search crumb should contain previous search');
        $this->assertEquals('Model: ABC', $r->SearchBreadcrumbs->last()->Title,     'Search crumb should contain current search');

        $r = $s->search(array(
            'q'     => 'green',
            '__ps'  => $r->SearchLogID,
            '__t'   => 'Price: $10.50',
            'f' => array(
                'Model' => 'ABC',
                'Price' => '10.50',
            ),
        ));
        $this->assertEquals(3, $r->SearchBreadcrumbs->count(),                       'Search crumb should have 3 entries');
        $this->assertEquals('Search: green', $r->SearchBreadcrumbs->first()->Title,  'Search crumb should contain first search');
        $this->assertEquals('Model: ABC', $r->SearchBreadcrumbs->offsetGet(1)->Title, 'Search crumb should contain previous search');
        $this->assertEquals('Price: $10.50', $r->SearchBreadcrumbs->last()->Title,   'Search crumb should contain current search');

        $r = $s->search(array('q' => 'purple'));
        $this->assertEquals(1, $r->SearchBreadcrumbs->count(),                       'Search crumb should reset');
    }


    public function testSuggestions()
    {
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
    public function testFacets()
    {
        foreach (Page::get() as $p) {
            $p->publish('Stage', 'Live');
        }

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

        // Given a search for 'green' with 1 facet ............................................
        $r = $s->search(array('q' => 'green'));
        $this->assertEquals(2, $r->TotalMatches,        'Should contain 2 products');
        $this->assertNotEmpty($r->Facets,               'Facets should be present');
        $this->assertEquals(1, $r->Facets->count(),     'There should be one facet');
        $model = $r->Facets->first();
        $this->assertEquals('By Model', $model->Label,  'Label should be correct');
        $this->assertEquals(2, $model->Values->count(), 'Should be 2 values');
        $model1 = $model->Values->first();
        $this->assertEquals('ABC', $model1->Label,      'Value label should be correct');
        $this->assertEquals(1, $model1->Count,          'Value count should be correct');

        // Given a search with price and category facets ......................................
        Config::inst()->update('ShopSearch', 'facets', array(
            'Model'     => 'By Model',
            'Price'     => array(
                'Label'       => 'By Price',
                'Type'        => 'Link',
                'LabelFormat' => 'Currency',
            ),
            'Category'  => 'By Category',
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

        // Given a search with a range facet for price and checkboxes for category ..........
        Config::inst()->remove('ShopSearch', 'facets');
        Config::inst()->update('ShopSearch', 'facets', array(
            'Price' => array(
                'Label' => 'By Price',
                'Type'  => ShopSearch::FACET_TYPE_RANGE,
            ),
            'Category' => array(
                'Label'  => 'By Category',
                'Type'   => ShopSearch::FACET_TYPE_CHECKBOX,
                'Values' => 'ShopSearch::get_category_hierarchy()',
            ),
        ));

        $params = array('q' => '');
        $r = $s->search($params);
        $r->Facets = FacetHelper::inst()->insertFacetLinks($r->Facets, $params, 'http://localhost/');
        $this->assertEquals(2, $r->Facets->count(),     'There should be 2 facets');
        $category = $r->Facets->last();
        $this->assertEquals(4, $category->Values->count(),      'There should be a value for each category');
        $this->assertTrue($category->Values->first()->Active,   'They should all be checked');
        $this->assertTrue($category->Values->last()->Active,    'They should all be checked (2)');
        $this->assertTrue($category->Values->offsetGet(1)->Active,   'They should all be checked (3)');
        $this->assertTrue($category->Values->offsetGet(2)->Active,   'They should all be checked (4)');
        $url = parse_url($category->Values->first()->Link);
        parse_str($url['query'], $qs);
        $this->assertTrue(empty($qs['f']),             'Link should be all the other categories checked (2)');
        // This is all handled in javascript now.
//		$this->assertTrue(is_array($qs['f']['Category']),   'Link should be all the other categories checked (3)');
//		$this->assertFalse(in_array($this->idFromFixture('ProductCategory', 'c1'), $qs['f']['Category']), 'Link should be all the other categories checked (4)');
//		$this->assertTrue(in_array($this->idFromFixture('ProductCategory', 'c2'), $qs['f']['Category']), 'Link should be all the other categories checked (5)');
//		$this->assertTrue(in_array($this->idFromFixture('ProductCategory', 'c3'), $qs['f']['Category']), 'Link should be all the other categories checked (6)');
//		$this->assertTrue(in_array($this->idFromFixture('ProductCategory', 'c4'), $qs['f']['Category']), 'Link should be all the other categories checked (7)');
        $price = $r->Facets->first();
        $this->assertEquals(5, $price->MinValue,            'Price minimum value');
        $this->assertEquals(5000, $price->MaxValue,         'Price maximum value');
        $this->assertContains('RANGEFACETVALUE', $price->Link, 'Link should leave placeholder for slider value');
    }


    public function testFilters()
    {
        VirtualFieldIndex::build('Product');

        // one filter
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Model' => 'ABC'
            )
        ));
        $this->assertEquals(2, $r->TotalMatches,                'Should contain 2 products');
        $this->assertEquals('ABC', $r->Matches->first()->Model, 'Should actually match');

        // two filters
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Model' => 'ABC',
                'Price' => 10.50,
            )
        ));
        $this->assertEquals(1, $r->TotalMatches,                'Should contain 1 product');
        $this->assertEquals('ABC', $r->Matches->first()->Model, 'Should actually match');
        $this->assertEquals(10.5, $r->Matches->first()->sellingPrice(), 'Should actually match');

        // filter on category
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Category' => $this->idFromFixture('ProductCategory', 'c3'),
            )
        ));
        $this->assertEquals(3, $r->TotalMatches,                'Should contain 3 products');

        // filter on multiple categories
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Category' => array(
                    $this->idFromFixture('ProductCategory', 'c1'),
                    $this->idFromFixture('ProductCategory', 'c3'),
                ),
            ),
        ));
        $this->assertEquals(4, $r->TotalMatches,                'Should contain all products');

        // filter on multiple categories with comma separation
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Category' => 'LIST~' . implode(',', array(
                    $this->idFromFixture('ProductCategory', 'c1'),
                    $this->idFromFixture('ProductCategory', 'c3'),
                )),
            ),
        ));
        $this->assertEquals(4, $r->TotalMatches,                'Should contain all products');

        // filter on price range
        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Price' => 'RANGE~8~12'
            ),
        ));
        $this->assertEquals(1, $r->TotalMatches,                'Should contain only 1 product');
        $this->assertEquals($this->idFromFixture('Product', 'p2'), $r->Matches->first()->ID, 'Match should be p2');

        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Price' => 'RANGE~-3~4'
            ),
        ));
        $this->assertEquals(0, $r->TotalMatches,                'Empty matches work on the low end');

        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Price' => 'RANGE~5555~10000'
            ),
        ));
        $this->assertEquals(0, $r->TotalMatches,                'Empty matches work on the high end');

        $r = ShopSearch::inst()->search(array(
            'f' => array(
                'Price' => 'RANGE~12~8'
            ),
        ));
        $this->assertEquals(0, $r->TotalMatches,                'A flipped range does not cause error');
    }


    public function testVFI()
    {
        // Given a simple definition, spec should be properly fleshed out
        $spec = VirtualFieldIndex::get_vfi_spec('Product');
        $this->assertEquals('simple', $spec['Price2']['Type']);
        $this->assertEquals('all', $spec['Price2']['DependsOn']);
        $this->assertEquals('sellingPrice', $spec['Price2']['Source']);

        // Given a simple array definition, spec should be properly fleshed out
        $spec = VirtualFieldIndex::get_vfi_spec('Product');
        $this->assertEquals('list', $spec['Category']['Type']);
        $this->assertEquals('all', $spec['Category']['DependsOn']);
        $this->assertEquals('Parent', $spec['Category']['Source'][0]);

        // build the vfi just in case
        VirtualFieldIndex::build('Product');
        $p = $this->objFromFixture('Product', 'p4');
        $cats = new ArrayList(array(
            $this->objFromFixture('ProductCategory', 'c1'),
            $this->objFromFixture('ProductCategory', 'c2'),
            $this->objFromFixture('ProductCategory', 'c3'),
        ));

        // vfi fields should be present and correct
        $this->assertTrue($p->hasField('VFI_Price'),    'Price index exists');
        $this->assertEquals(5, $p->VFI_Price,           'Price is correct');
        $this->assertTrue($p->hasField('VFI_Category'), 'Category index exists');
        $this->assertEquals('>ProductCategory|' . implode('|', $cats->column('ID')) . '|', $p->VFI_Category,
            'Category index is correct');

        // vfi accessors work
        $this->assertEquals(5, $p->getVFI('Price'),         'Simple getter works');
        $this->assertEquals($cats->toArray(), $p->getVFI('Category'),  'List getter works');
        $this->assertNull($p->getVFI('NonExistentField'),   'Non existent field should return null');
    }


    public function testFacetsOnCategory()
    {
        VirtualFieldIndex::build('Product');
        foreach (Product::get() as $p) {
            $p->publish('Stage', 'Live');
        }

        $c      = $this->objFromFixture('ProductCategory', 'c3');
        $c->publish('Stage', 'Live');
        $prods  = $c->ProductsShowable();
        $this->assertEquals(3, $prods->count(),         'There are initially 3 products in the category');

        $prods  = FacetHelper::inst()->addFiltersToDataList($c->ProductsShowable(), array(
            'Price'     => 'RANGE~1~10',
        ));
        $this->assertEquals(2, $prods->count(),         'Should be 2 products after a price range');

//		$prods  = $c->addFiltersToDataList($c->ProductsShowable(), array(
//			'Category'  => $this->idFromFixture('ProductCategory', 'c1'),
//		));
//		Debug::dump($prods->dataQuery()->sql());
//		$this->assertEquals(1, $prods->count(),         'Should be 1 product also in c1');

        $facets = FacetHelper::inst()->buildFacets($prods, array(
            'Price' => array(
                'Label' => 'By Price',
                'Type'  => ShopSearch::FACET_TYPE_RANGE,
            ),
            'Category' => array(
                'Label'  => 'By Category',
                'Type'   => ShopSearch::FACET_TYPE_CHECKBOX,
                'Values' => 'ShopSearch::get_category_hierarchy()',
            ),
        ));
        $this->assertEquals(2, $facets->count(),        'Should be 2 facets');
    }


    public function testStaticAttributes()
    {
        VirtualFieldIndex::build('Product');
        foreach (Product::get() as $p) {
            $p->publish('Stage', 'Live');
        }
        $c = $this->objFromFixture('ProductCategory', 'c3');
        $c->publish('Stage', 'Live');

        // set up some attributes
        $p1     = $this->objFromFixture('Product', 'p1');
        $p2     = $this->objFromFixture('Product', 'p2');
        $pat1   = $this->objFromFixture('ProductAttributeType', 'pat1');
        $pat1v1 = $this->objFromFixture('ProductAttributeValue', 'pat1v1');
        $pat1v2 = $this->objFromFixture('ProductAttributeValue', 'pat1v2');
        $p1->StaticAttributeTypes()->add($pat1);
        $p1->StaticAttributeValues()->add($pat1v1);
        $p1->StaticAttributeValues()->add($pat1v2);
        $p2->StaticAttributeTypes()->add($pat1);
        $p2->StaticAttributeValues()->add($pat1v1);

        // Should be able to filter by an attribute
        $attkey = 'ATT' . $pat1->ID;
        $prods  = FacetHelper::inst()->addFiltersToDataList($c->ProductsShowable(), array($attkey => $pat1v1->ID));
        $this->assertEquals(2, $prods->count(), 'Should be 2 products for v1');
        $prods  = FacetHelper::inst()->addFiltersToDataList($c->ProductsShowable(), array($attkey => $pat1v2->ID));
        $this->assertEquals(1, $prods->count(), 'Should be 1 product for v2');

        // Should be able to facet by ATT1 explicitly
        $facets = FacetHelper::inst()->buildFacets($c->ProductsShowable(), array(
            $attkey => array(
                'Label' => 'By Color',
                'Type'  => ShopSearch::FACET_TYPE_LINK,
            ),
        ));
        $this->assertEquals(1, $facets->count(),        'Should be 1 facet');
        $f1 = $facets->First();
        $this->assertEquals(2, $f1->Values->count(),    'Should be 2 values');
        $this->assertEquals('Red', $f1->Values->First()->Label);
        $this->assertEquals(2, $f1->Values->First()->Count);
        $this->assertEquals('Green', $f1->Values->Last()->Label);
        $this->assertEquals(1, $f1->Values->Last()->Count);

        // Should be able to facet by auto_facet_attributes
        $facets = FacetHelper::inst()->buildFacets($c->ProductsShowable(), array(), true);
        $this->assertEquals(1, $facets->count(),        'Should be 1 facet');
        $f1 = $facets->First();
        $this->assertEquals(2, $f1->Values->count(),    'Should be 2 values');
        $this->assertEquals('Red', $f1->Values->First()->Label);
        $this->assertEquals(2, $f1->Values->First()->Count);
        $this->assertEquals('Green', $f1->Values->Last()->Label);
        $this->assertEquals(1, $f1->Values->Last()->Count);
    }
}
