Summary of Adapters
===================

The adapter can be set with the following YML:
```
ShopSearch:
  adapter_class: ShopSearchSolr
```

ShopSearchSimple
----------------
This module is for quick, out of the box, development/testing usage.
It doesn't really do any full-text search, just uses the built-in
:PartialMatch filter of SilverStripe's DataObjects. It's likely to be
quite slow and not too smart, especially given a lot of products.

It is the default adapter and is does have the same features as the
others, though.

**Setup**
Use searchable_fields config/static on your model(s).


ShopSearchMysql
---------------
This module uses MySQL's fulltext searching.

NOTE: It's not required that you use SilverStripe's FullTextSearchable
extension along with this. It could work, but may also cause conflicts.
That extension is fine but you're limited to searching SiteTree and File.

**Setup**
If you're not using FullTextSearchable, set up a fulltext index like so:
```
class MyModel extends DataObject {
	// ...

	private static $indexes => array(
		'SearchFields' => array(
			'type' => 'fulltext',
			'name' => 'SearchFields',
			'value' => 'Title,Content',
		)
	);

	// ...
}
```
The adapter will look for the "SearchFields" index.


ShopSearchSolr
--------------
This adapter is not feature complete and may not actually work. To
get going, see the docs for setting up Solr on the fulltextsearch
module and go from there.