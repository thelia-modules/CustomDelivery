# 4.0.5

- Activating the module no longer empties the shipping slices of a database that already holds them (a shop upgraded
  from an older Thelia, where the `is_initialized` flag is missing): the install script runs without its
  `DROP TABLE` statement and with `CREATE TABLE IF NOT EXISTS` (`Service/InstallSql`, unit tested).
- Known, not handled here: creating the table commits the transaction `BaseModule::activate()` opens around
  `postActivation()` (implicit commit of a MySQL DDL statement).

# 4.0.2

- Fixed the back-office configuration page rendering another module's configuration: the Twig
  templates are now scoped under `templates/backOffice/default-twig/CustomDelivery/` so they can no
  longer be shadowed by a same-named template shipped by another active module.

# 1.0.7

- The dispatcher is now passed to Session::getSessionCart()

# 1.0.3

- Fixed stability tag in module.xml

# 1.0.2

- Removed bootbox dependency
- Added alert when no shipping zones defined
- Better float validation

# 1.0.1

- Resolve #4 Add js dependency bootbox
