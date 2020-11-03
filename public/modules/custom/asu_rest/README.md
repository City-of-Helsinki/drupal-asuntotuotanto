#ASU - REST

Module for different APIs exposed for external systems.

###Filters 

- Mainly used by react apartment filtering widget.
- Returns filters that can be used to filter data indexed in ElasticSearch.
  - Filters are returned as json.
  - Data is translated by langcode given in url.
  

```
example:

GET /{fi/en/sv}/filters

Parameters: 
None

returns: 
{
  {
    "elastic_index_field_name": {
      "label": "Title for the filter",
      "items": ["First value to use as a filter", "Second value to use as a filter", ...],
      "suffix": NULL
    },
    "living_area": {
      "label": "Pinta-ala / m2",
      "items": ["Enintään", "Vähintään2],
      "suffix": "m2"
    }, ...
  }
}
```

### Mailing list
```
Example:

POST /fi/en/sv/mailinglist

Parameters:
- string  : user_email (required field) - "example@example.com"
- int     : project_id (required field) - 32
- Boolean : subscribe_mailinglist       - 1/0, "true"/"false"

By default subscribe_mailinglist is false.

Returns: Status code with success / error message.
200 : OK
400 : Missing required field: {fieldname}
422 : Data is not valid: {fieldaname}

Future:
404 : Resource not found :: Either the project is not found or the premarketing start time has already gone.
```
