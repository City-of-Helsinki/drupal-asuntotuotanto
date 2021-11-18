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

Returns: Success/error message with appropriate status code.
200 : OK
400 : Missing required field: {fieldname}
422 : Data is not valid: {fieldaname}

Future:
404 : Resource not found :: Either the project is not found or the premarketing start time has already gone.
```

###Elasticseach

- Query the apartments indexed in elasticsrach

```
Example:
POST /{fi/en/sv}/elasticsearch

Parameters: (* = mandatory field)
- project_ownership_type: string - "hitas"
- project_district      : array  - ["Kaarela", "Käpylä"]
- project_state_of_sale : array  - ["FOR_SALE", "READY", "UPCOMING"]
- room_count            : array  - [2,3,5]
- living_area           : array  - [0, 9999]  # min and max
- price                 : int    - 12399900   # requires project_ownership_type value
- project_has_sauna     : bool   - true/false # property
- has_apartment_sauna   : bool   - true/false # property
- has_terrace           : bool   - true/false # property
- has_yard              : bool   - true/false # property
- has_balcony           : bool   - true/false # property


Returns: Success/error message with appropriate status code.
200 : OK
500 : price field without project ownership type
500 : parameter is of wrong type

```
