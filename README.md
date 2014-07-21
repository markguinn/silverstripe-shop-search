Improved Search for Silverstripe Shop Submodule
===============================================

[![Build Status](https://secure.travis-ci.org/markguinn/silverstripe-shop-search.png)](http://travis-ci.org/markguinn/silverstripe-shop-search)

Provides more advanced search features that are common on e-commerce
sites. It is intended for use with the Shop <https://github.com/burnbright/silverstripe-shop>
module, but could probably be used in other contexts. There are few, if
any dependencies on shop, and most of that has to do with configuration
and could be worked around.


FEATURES:
---------
- Keyword search
- Remember search history
- Search suggestions
- Search-as-you-type (i.e. products displayed along with search suggestions)
- Several types of filters and facets (link, checkboxes, range sliders)
- Supports MySQL fulltext, Solr, or simple DataList :PartialMatch filtering
- Category pages can also be faceted if desired


REQUIREMENTS:
-------------
- Silverstripe 3.1 (may work with 3.2/master but untested)
- Shop Module 1.0
- Fulltextsearch module (if using solr)


INSTALLATION:
-------------
1. Install via composer
2. Make adjustments to configuration via yml. May want to change adapter
   class, searchable classes, and facets. **See shop_search/docs/en/Adapters.md**.
3. If using VirtualFieldIndex class for faceting with mysql, enable a
   cron job for `dev/tasks/BuildVFI`.


TODO:
-----
- Tests for Solr adapter
- Update documentation for Solr adapter


DEVELOPERS:
-----------
* Mark Guinn - mark@adaircreative.com

Pull requests always welcome. Follow Silverstripe coding standards.


LICENSE (MIT):
--------------
Copyright (c) 2013 Mark Guinn

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the
Software, and to permit persons to whom the Software is furnished to do so, subject
to the following conditions:

The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.
