Virtual Field Index
===================

In order to facilitate things like searching and sorting on calculated fields,
there is a helper class included called VirtualFieldIndex. It allows you
to set up index fields that contain calculated data, which update on write
and/or with a cron job. The main use cases of this are:

1. Categories - since a product can be in multiple categories it's difficult
   to search on that. The index field looks like this: `>ProductCategory|1|4|5|`
   which means you can just do a LIKE search for "|4|" to get all products
   in category 4.
2. Pricing - if you're adding any kind of discounts (which most ecommerce sites
   do), the price of a product is not necessarily what is in the BasePrice field.
   You can add a VFI for Price and even one for "savings amount" or "retail price".

Each VFI gets a db field prefixed with "VFI_" so they're easy to distinguish.


EXAMPLE
-------
In mysite/_config/config.yml:

```
Product:
  extensions:
    - VirtualFieldIndex
VirtualFieldIndex
  vfi_spec:
    Product:
      Price:
        Type: simple
        Source: sellingPrice
        DependsOn: BasePrice
        DBField: Currency
      Categories:
        Type: list
        DependsOn: all
        Source:
          - ParentID
          - ProductCategories.ID
```

The above will create two new fields on Product: VFI_Price and VFI_Categories.
These will be updated whenever the object is changed and can be triggered via
a build task (BuildVFI).

The categories index will contain the merging of results from ParentID and
ProductCategories.ID in the form of a comma-delimited list.

NOTE: having multiple sources doesn't equate with Type=list always. That's
just the default. Type=list means the output is a list. A single source could
also return an array and that would be a list as well.

You may then access the fields as such:

```
$product->getVFI('Price');      // returns a Currency object
$product->getVFI('Category');   // returns an array of ProductCategory objects
```


CRON TASK
---------
You'll want to set up a cron task that runs pretty often. Something like:

```
0 * * * * /var/www/sitename/public/framework/sake dev/tasks/BuildVFI >/dev/null 2>&1
```
