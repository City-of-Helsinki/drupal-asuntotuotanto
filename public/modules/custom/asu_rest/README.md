#ASU - REST

Custom module which extends core REST module.

## Endpoints

###Filters 

- Mainly used by react apartment filtering widget.
- Returns filters that can be used to filter data indexed in ElasticSearch.
  - Filters are returned as json.
  - Supports translations.

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

- Adding users to mailinglist.
- User can:
  - Request a notification for certain project. 
  - Request to be added to a mailinglist.
  
```
Example:
POST /fi/en/sv/mailinglist

Parameters: (* = mandatory field)
- user_email            : * string   - "example@example.com"
- project_id            : * int      - 32
- subscribe_mailinglist : boolean    - 1/0, "true"/"false"

Returns: Success/error message with approproate status code.
200 : OK
400 : Missing required field: {fieldname}
422 : Data is not valid: {fieldaname}

Future:
404 : Resource not found :: Either the project is not found or the premarketing start time has already gone.
```
