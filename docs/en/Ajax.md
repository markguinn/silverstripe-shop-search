Ajax Features
=============

## Search Suggestions

Search suggestions comes out of the box. You don't have to do anything to enable that.


## Shop Ajax Integration

We're working on some more advanced ajax features for the shop module. The
current home of that work is here:

<https://github.com/markguinn/silverstripe-shop/tree/feature-ajax-fresh>

If/when that gets merged, you can add the following:

```
Controller:
  extensions:
    - ShopSearchAjax
```

And start to take easy advantage of ajax for paging / filtering / etc.
