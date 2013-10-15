Improved Search for Silverstripe Shop Submodule
===============================================

[![Build Status](https://secure.travis-ci.org/markguinn/silverstripe-shop-search.png)](http://travis-ci.org/markguinn/silverstripe-shop-search)

This is very, very early stages. Probably not suitable for use yet.
Full features + documentation coming soon. Check the comments for now.

REQUIREMENTS:
-------------
- Keyword search on multiple fields
- Remember search history
- Search suggestions
- Filter based on attributes (i.e. facet)
	- Facets need to be able to be different for different categories
	- Some will be boolean and others enumeration of values


INSTALLATION:
-------------
1. Install via composer
2. Make adjustments to configuration via yml. May want to change adapter
   class, searchable classes, and facets.
3. If using VirtualFieldIndex class for faceting with mysql, enable a
   cron job for `dev/tasks/BuildVFI`.


TODO:
-----
- Better field selection for search
- Define filters manually or based on available variations/attributes
- Faceting on Solr
- Tests for Solr adapter
- Figure out how to handle category filtering and parent/sub cats

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
