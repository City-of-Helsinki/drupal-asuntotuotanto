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
