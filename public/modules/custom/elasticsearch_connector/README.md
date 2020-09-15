# NOTICE

###### When this module is updated for drupal 9:

1. Delete this custom module

2. "Composer remove nodespark/des-connector" (elastic_connector dependency)

3. "Composer require drupal\elastic_connector"

4. If updated version doesn't have "Nested Entity processor" -plugin, patch it (src/Plugin/search_api/processor/NestedEntity.php).
https://www.drupal.org/files/issues/2019-03-26/3043047-support-automated-nested-entities-4.patch
