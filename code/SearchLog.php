<?php
/**
 * Records search results for future suggestions and analysis
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 09.20.2013
 * @package shop_search
 */
class SearchLog extends DataObject
{
	private static $db = array(
		'Query'         => 'Varchar(255)',
		'Filters'       => 'Text', // json
		'NumResults'    => 'Int',
	);

	private static $has_one = array(
		'Member'        => 'Member',
		'ParentSearch'  => 'SearchLog', // used in constructing a search breadcrumb
	);
}
