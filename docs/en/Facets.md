Facets
======

Facets are a common feature of modern ecommerce sites. There are
currently 3 types:

* Link Facets - display a list of links in the sidebar for each
  value of a faceted field within the search. For example, you
  a search for "shirts" my list all the available sizes with
  a count for each size. Clicking the links would narrow the search.
* Checkbox Facets - display similarly to links but use checkboxes.
  As such, you must define all the available options ahead of time.
* Range Facets - display a slider. Most useful for things like price.


Example
-------
This example assumes you have already set up virtual field indexes
on Price and Category (See VirtualFieldIndex.md). VFI is not necessary
for facets.

In config.yml:

```
ShopSearch:
  facets:
    Model: 'Model Number'
    Price:
      Label: Price
      Type: range
      RangeMin: 0
      RangeMax: 2000
      LabelFormat: Currency
    Category:
      Label: Category
      Type: checkbox
      Values: 'ShopSearch::get_category_hierarchy()'
```

The above sets up a simple link facet on the "model" field (a
strange example, sorry), and slightly more complex range and
checkbox facets for category.

You would then just include something like this:

```
<% include Facets %>
```

In the sidebar of Page_results.ss.


### Options for LabelFormat

These are currently only present on Range type facets. Other types
use the underlying DB field for formatting.

* Currency
* Percentage
* Percentage100 - same as above but expects 0-100 instead of 0.0-1.0

TODO: these options should be implemented more broadly and flexibly
