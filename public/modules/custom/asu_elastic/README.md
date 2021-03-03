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


## Development

If ElasticSearch Docker container is running you can query the indexed data
 - Open your browser
 - Browse to address 0.0.0.0:9200/_search
 - You can add queries as queryparameter: for example ?size=100 return 100 results.
