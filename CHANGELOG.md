# CHANGELOG Boxalino Magento 1.x v2 plugin 

All further changes to the plugin will be added to the changelog.
On every plugin update - please check the file and what needs to be tested on your system.

If you have any question, just contact us at support@boxalino.com

### Version History
**[v1.4.8 : 2019-06-26](#v1.4.8)**<br>
**[v1.4.7 : 2019-05-14](#v1.4.7)**<br>
**[v1.4.6 : 2019-05-10](#v1.4.6)**<br>

<a name="v1.4.8"></a>
### v1.4.8 : 2019-06-26
##### 1. SEO-friendly filter names for custom Boxalino Fields
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Advanced -> SEO-friendly filters mapping"
* _description_ : For custom magento fields (ex: bx_discountedPrice), there is the possiblity of making user-friendly filter names, per store-view
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/37108d40198061b3b63eb82b8c9882250b4f5d27

<a name="v1.4.7"></a>
### v1.4.7 : 2019-05-14
##### 1. Extra parameters on narrative request
* _description_ : The extra-data pre-set on narrative request can be used to control the narrative output.
In your XML definition of the block, set the parameter _extended_request_ to _true_ and create an observer to set data.
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/61581505c3790b2343e549d8bf58635523d81f09

<a name="v1.4.6"></a>
### v1.4.6 : 2019-05-10
##### 1. Conditional on using the root category as a filter (on search)
* *default Magento behavior* : By default, the products not belonging to a category are searchable and appear in the search results.
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Advanced -> Only show products tagged to a category"
* _test_ : If it is set to "yes" - the products which do not belong to a category will not be displayed.
**Default - "no"**: the root category is not used as a filter.

##### 2. Conditional on displaying the child-categories navigation menu (on navigation)
* *default Magento behavior* : By default, on a parent category, the layered navigation with child categories is visible.
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Navigation -> Show the child categories navigation view"
* *test* : 
If it is set to "yes" - the child categories on the anchor parent will be displayed.
**Default -  "no"** : the categories are not visible as a filter, unless it is force-included from your Boxalino admin facets configuration.

##### 3. Conditional on redirecting to the child category(on navigation)
* *default Magento behavior* : By default, on Magento layered navigation, when selecting a child-category, it is applied as a filter.
* *configuration* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Navigation -> Redirect to category view on category select"
* *test* : If it is set to "yes" - when a child category is selected from the layered navigation - the user will be redirected to that category view.
**Default - "no"**