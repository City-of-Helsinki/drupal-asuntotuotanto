# Asu - Elastic

Module contains modifications and extensions to SearchApi and ElasticSearch modules.


## Functionalities

### Custom SearchApi data types

SearchApi data types can be used to easily alter the indexed data:
 - Create new datatype
 - Add new datatype to asu_elastic.module "data_types" -array.
 - Add newly created datatype to indexed field in SearchApi -module configurations. 

### Adding computed fields to elastic index

Computed fields are handled by asu_content -module. See asu_computed README.md
