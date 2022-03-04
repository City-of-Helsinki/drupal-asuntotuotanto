# asu_user

This module contains user-specific functionalities.

### registration form customizations

Default registration form has been overridden with custom form
with customized functionalities.

Check .module file & RegisterForm.php

### User computed fields

User has multiple fields which values are saved separately in external service.
These values are shown using computed fields.

- On user login, the user data is fetched via API
  - The user data is saved on private temporary storage
- Computed fields use the data through the temporary storage
- Computed fields and field mappings can be found in configurations

### Login form customization

Default login form has been overridden with custom form with customized functionalities.

### User bundles

User bundles are enabled with user bundle contrib module. All users should be in either salesperson or customer bundle.

## User registering & login flows

### Customer registration with helsinki profiili

- User can register by authenticating in helsinki profiili.
- Integration is handled by helfi_tunnistamo contrib module.
  - tunnistamo module is based on openid_connect contrib module.
- New Drupal user is created after successful profiili-authentication
  - Profiili returns few user data fields: Email, given name, family name, tunnistamo's own user id(uuid), email_verified
- After new Drupal user is created, the user information is sent to Django backend
  - As a response to a CreateUserRequest, backend profile id & backend password are returned and saved to customer user
    - Backend profile id & password can be used to request for an auth token.
    - Auth token is to be used on any authenticated endpoint request.
    - If token is invalid (for example too old) new token is requested automatically before the actual request is sent.
    - Token is held in users session.
  - Most of Backend API requests requires authentication.
  - Backend holds majority of user's data (check external asu_user.external_user_fields configuration)
- On login, user is automatically authenticated to backend api and any subsequent request can use the auth token given by backend authentication
  - Auth token is JWT token which TTL is 30 minutes
  - If token dies during user session, new token is requested automatically before the actual request is sent.

### Customer registration by salesperson

- Salesperson can create new customers on behalf of the customer by using the normal Drupal way.
- Customer created by salesperson is handled almost the same as if customer had registered by themselves.
  - Backend profile is created by sending the data to Backend etc.
  - Email validation is not required from the customer.
- Customer may receive email about the new user account. Customer can also login to the account if they know the credentials.


### Customer login

- Customer logs in to Drupal
- After succesful login the user is automatically authenticated to backend api
  - Token is fetched and set to user's session
  - User data held in backend is also fetched and shown to on user account / edit pages.
- A backend profile is created if customer for some reason doesn't have backend profile.
  - (= if user doesn't have the credentials saved for the user)
- If customer's email has not been verified, a verification email is sent. Customer is informed about this.
