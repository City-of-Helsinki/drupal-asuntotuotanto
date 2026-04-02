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
- project_ownership_type: * string - "hitas" or "haso" (mandatory for all requests)
- project_district      : array  - ["Kaarela", "Käpylä"]
- project_state_of_sale : array  - ["FOR_SALE", "READY", "UPCOMING"]
- room_count            : array  - [2,3,5]
- living_area           : array  - [0, 9999]  # min and max
- price                 : int    - 12399900   # requires project_ownership_type value
- properties            : array  - ['project_has_sauna','has_apartment_sauna','has_terrace']


Returns: Success/error message with appropriate status code.
200 : OK
500 : project_ownership_type is required
500 : parameter is of wrong type

```

### Elasticsearch-compatible search

The following endpoints return Elasticsearch-style JSON responses and are
secured with OAuth2 (Simple OAuth). Use the client credentials grant to obtain
an access token and send `Authorization: Bearer <token>` on requests.

- Token endpoint: `POST /oauth/token`
 - Configure Simple OAuth keys and a consumer with client credentials.

#### OAuth2 setup

Simple OAuth expects RSA keys at the paths configured in `simple_oauth.settings.yml`:
- `public_key`: `/app/keys/public.key`
- `private_key`: `/app/keys/private.key`

Generate keys (e.g. for local development):
```bash
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key
```

Create the `/app/keys` directory and place the keys there. In Docker, mount the
keys directory or copy keys into the container. Keys are gitignored (`/keys`, `*.key`).

#### OAuth consumer (machine client)

Simple OAuth **consumers** are stored as entities, not config export, so they are
not shipped as `*.yml` in `conf/cmi`. This module can **provision** the consumer
for the apartment-application-service (or any caller using the same client ID)
when:

- `asu_rest.settings` → `oauth_consumer.auto_create` is `true` (default in CMI),
- the `rest_client` OAuth2 scope config exists,
- and the environment variable **`ASU_REST_OAUTH_CLIENT_SECRET`** is set at
  install/update time (never commit this value).

Optional: **`ASU_REST_OAUTH_CLIENT_ID`** overrides the configured `client_id`
(default `apartment_application_service`).

After deployment, run `drush updb` (or install the site) with the secret set; the
consumer is created once. Match the same client ID and secret in Django
`DRUPAL_SEARCH_API_CLIENT_ID` / `DRUPAL_SEARCH_API_CLIENT_SECRET`.

To disable auto-provisioning, set `oauth_consumer.auto_create` to `false` in
`asu_rest.settings` and manage the consumer in the UI instead.

#### CORS and APP_ENV

When `APP_ENV` is `testing` or `dev`, permissive CORS headers are added for local
development. **Never use `APP_ENV=dev` in production**; it allows cross-origin
requests from any origin.

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
