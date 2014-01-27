Static Attributes
=================

This is an extension that can optionally be added to Product (or a subclass)
to allow the same ProductAttributeType and ProductAttributeValue record used
with variations to be assigned to products. This allows easy filtering in a
few cases:

1. Products that may require different attributes to be displayed for different
   types of products, but need those attributes to be CMS selected rather than
   determined by the developer on the model.
2. Products with variations where you want to facet/filter on which variations are
   available - (i.e. show me all shirts that have an XS size available).

It works very similar to variations.
* Products have an "attributes" tab.
* You select which attribute types (e.g. color, size, etc) apply to the product and save.
* You can then select one or more attribute values for each attribute type


EXAMPLE
-------
In yml config:

```
Product:
  extensions:
    - HasStaticAttributes
  default_attributes:
    - 8
    - 9
    - 10
```

In the above example, `default_attributes` is optional and would apply 3 attribute types
to any newly created product by default. 8, 9, and 10 would be the ID's in the database.


FACETING
--------
If you're not using Solr you can then facet on static attributes just like any other field
by using ATT8 (where 8 would be the ID of the product attribute type) as the field name.

Additionally, you can automatically facet any available attributes by setting the
`auto_facet_attributes` option to true on FacetedCategory or ShopSearch.


NOTE
----
I don't love using the type ID's all over the place like that, but to my mind it's better
than using the name because the name can get changed in the CMS. I'm very open to feedback
and suggestions on how to do that better.

