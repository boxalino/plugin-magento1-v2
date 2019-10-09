# CHANGELOG Boxalino Magento 1.x v2 plugin 

All further changes to the plugin will be added to the changelog.
On every plugin update - please check the file and what needs to be tested on your system.

If you have any question, just contact us at support@boxalino.com

### Version History
**[v2.1.0 : 2019-09-30](#v2.1.0)**<br>
**[v2.0.0 : 2019-09-30](#v2.0.0)**<br>
**[v1.5.0 : 2019-08-07](#v1.5.0)**<br>
**[v1.4.9 : 2019-07-02](#v1.4.9)**<br>
**[v1.4.8 : 2019-06-26](#v1.4.8)**<br>
**[v1.4.7 : 2019-05-14](#v1.4.7)**<br>
**[v1.4.6 : 2019-05-10](#v1.4.6)**<br>

<a name="v2.1.0"></a>
### v2.1.0 : 2019-10-08
_post-deployment integration test_: 
* on navigation - check product visibility/status combinations for grouped and configurable products
 
##### 1. Exporter - stock&visibility update for configurable/grouped products
* _description_ : The product visibility is exported as is; The stock of the parent depends on the in-stock children as well; The children-parent relation influences the visibility and status of the product. 
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/40aa4dee6bb63400c7bdee693c3deb3570a0f6a9
https://github.com/boxalino/plugin-magento1-v2/commit/b1bddb568917f0120408a9c201f81ee4026ea21a
https://github.com/boxalino/plugin-magento1-v2/commit/497ac30bda4bf16b0ba01af6f1397f95ec9cbd1c

##### 2. User-Friendly view for debugging Boxalino responses
* _description_ : For developers - use &boxalino_response=true OR boxalino_request=true as an URL parameter to see the content requested/returned by the SOLR index as JSON.
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/1bbeccb7bd2cacad41763d3f443a31790954a9f4


<a name="v2.0.0"></a>
### v2.0.0 : 2019-09-30
_post-deployment integration test_: 
* on navigation - check product visibility/status combinations for grouped and configurable products

##### 1. Exporter - visibility&status update for configurable/grouped products
* _description_ : : The SQL logic for retrieving product properties has been exported into a resource file. 
It can be overwritten for custom logic: 
https://github.com/boxalino/plugin-magento1-v2/blob/master/app/code/community/Boxalino/Intelligence/Model/Mysql4/Exporter.php 
The children-parent relation influences the visibility and status of the product.
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/a114910d3d96597921d1a42afea718867a0739cb



<a name="v1.5.0"></a>
### v1.5.0 : 2019-08-07
_post-deployment integration test_: 
* on navigation - check the right configurations for _category_ options are used
* on autocomplete - check the updates in the Block definition for _toHtml()
* 
##### 1. Navigation - option to show the category tree/facet
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Navigation", options: "Show the child categories navigation view"
* _description_ : If enabled - the category tree will be displayed on the navigation.
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/e511b3e75da797d22340cd2a8ae97ad5449df935

##### 2. Sort options on navigation fix
* _description_ : *bug fix* the sort options enabled on category (as configured in Magento admin) are used in the view
* _commits_: https://github.com/boxalino/plugin-magento1-v2/commit/283a9049567f4eed2e1f80edd03e76e46c916fcb
https://github.com/boxalino/plugin-magento1-v2/commit/5b9f0dc0dd442f91328b987ed773eebb451fe164

##### 3. Adding custom sorting options
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Advanced -> Custom sort option mapping"
* _description_ : If your system has created custom logic/fields for sorting, it has to be used. Map your system field (used for sorting) to a Boxalino field.
* _commits_ : https://github.com/boxalino/plugin-magento1-v2/commit/4c0e2284f3cc8329b9bba4ca1ff7d93765a7636e

##### 4. Autocomplete properties order by hit count
* _description_ : For the extra-properties used in autocomplete, the returned values will be sorted by count;
* _commits_ : https://github.com/boxalino/plugin-magento1-v2/commit/795fb6116ad8e89bd9864de832176d9fc36f88ef


<a name="v1.4.9"></a>
### v1.4.9 : 2019-07-02
_post-deployment integration test_: 
* if the autocomplete block/_toHtml() has been customized in your project, please check the extra configurations and layout logic;
* if you have customized Search Message elements for the response, enable the option
##### 1. Autocomplete - configurable location for categories matches, adding other property requests
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Autocomplete", options: "Show categories list after 1st textual suggestion", "Other properties query"
* _description_ : Configure if the categories matches to appear after the 1st recommendation or at the end; Configuration for adding custom property requests.
* _commit_: https://github.com/boxalino/plugin-magento1-v2/commit/efa24736f26a5330986095dfeda624affb674e47

##### 2. Search Message flag 
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Search Message -> Enable search messages block"
* _description_ : Enable/disable the Search Message response component;
* _commits_: https://github.com/boxalino/plugin-magento1-v2/commit/283a9049567f4eed2e1f80edd03e76e46c916fcb
https://github.com/boxalino/plugin-magento1-v2/commit/5b9f0dc0dd442f91328b987ed773eebb451fe164

##### 3. Advanced filter multiselect facet option 
* *configuration path* : "System->Configuration->Boxalino Extension-> Search-Navigation", tab "Advanced -> Multiselect Options As One"
* _description_ : By default - disabled; When enabled, the plugin will be programmed to expect a single string with the joined selected facets option via the configured delimiter. Check with us before enabling if it is needed for you store specifications.


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