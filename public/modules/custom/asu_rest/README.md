#ASU - REST

Custom module which extends core REST module.

## Endpoints

### Initialize

- Endpoint for react search tool for initialization

returns current user data, search filters etc.

### Elasticseach

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
- properties            : array  - ['project_has_sauna','has_apartment_sauna','has_terrace']


Returns: Success/error message with appropriate status code.
200 : OK
500 : price field without project ownership type
500 : parameter is of wrong type

```

### Elasticsearch-compatible search

The following endpoints return Elasticsearch-style JSON responses and are
secured with OAuth2 (Simple OAuth). Use the client credentials grant to obtain
an access token and send `Authorization: Bearer <token>` on requests.

- Token endpoint: `POST /oauth/token`
 - Configure Simple OAuth keys and a consumer with client credentials.

Endpoints:
- `GET /projects`
- `GET /projects/{project_id}`
- `GET /projects/{project_id}/apartments`
- `GET /apartments`
- `GET /apartments/{apartment_id}`

Search params match the legacy `/elasticsearch` endpoint (e.g.
`project_ownership_type`, `project_district`, `project_state_of_sale`,
`room_count`, `living_area`, `price`, `properties`).

### Mailing list
- Not done in MVP

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
