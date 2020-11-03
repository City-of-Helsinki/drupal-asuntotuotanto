# Asu content module

This module contains content and elasticsearch related modifications.

## Search api data type

Search api data types are located under plugin.


## Computed fields

Computed fields are created by using Computed Field Plugin -module.


#### Adding computed field to ElasticSearch index

Field won't be visible in ElasticSearch index UI
 - Edit conf/cmi/search_api.index.apartments.yml
 - Add configuration for your computed field manually (check some existing computed field).
 - After importing manually added configuration, you can see newly added field in index UI.
