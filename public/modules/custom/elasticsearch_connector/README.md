# NOTICE

###### When this module is updated for drupal 9:

1. Delete this custom module

2. "Composer remove nodespark/des-connector" (elastic_connector dependency)

3. "Composer require drupal\elastic_connector"

4. If updated version doesn't have "Nested Entity processor" -plugin, patch it (src/Plugin/search_api/processor/NestedEntity.php).
https://www.drupal.org/files/issues/2019-03-26/3043047-support-automated-nested-entities-4.patch

Applied patches: 
- https://www.drupal.org/project/elasticsearch_connector/issues/3110970
- https://www.drupal.org/files/issues/2020-05-28/3110970-drupal9-readiness.patch
- NOTICE PATCHES.txt https://www.drupal.org/files/issues/2018-09-06/elasticsearch_connector-array-value-fix-2977537-1.patch
