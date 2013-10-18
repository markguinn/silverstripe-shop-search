How to do some common things
============================

Disable Suggestions and/or Search-as-you-type
---------------------------------------------
In mysite/_config/config.yml:

```
ShopSearch:
  suggest_enabled: false
  search_as_you_type_enabled: false
```

Dropdown of Categories in the Search Bar
----------------------------------------
In mysite/_config/config.yml:

```
ShopSearchForm:
  category_dropdown: true
  category_empty_string: All Products
  category_field: 'f[Category]'
```

This assumes you already have a VFI set up on category (see VirtualFieldIndex.md).

