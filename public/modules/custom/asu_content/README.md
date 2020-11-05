# Asu content module

This module contains content related modifications.

### Computed fields

Computed fields are created by using Computed Field Plugin -module.

### CollectReverseEntity -service

Service that can be used to get related entity from node.

### Asu-logger -service

Logger configured for ASU-custom modules.

## Good to know

#### Adding computed field to ElasticSearch index

Computed fields won't be visible in SearchApi's index UI by default.
 - Go edit conf/cmi/search_api.index.apartments.yml
 - Add configuration for your computed field manually (look for an existing computed field for reference).
 - After importing manually added configuration (drush cim), you can see newly added field in index UI.
