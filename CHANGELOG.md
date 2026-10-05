# 4.1.0

- The tracking address is stored where Thelia reads the tracking address of a delivery module
  (module setting `tracking_url`). From Thelia 3.3 the core builds the tracking link of a Custom
  Delivery order from it, for the customer account, the back office, the order API and the
  shipping e-mail. The update copies the existing address there; nothing has to be typed again.
- The tracking address is optional and must be an `http(s)` address containing `%ID%`. The
  tracking number is url-encoded into it.
- From Thelia 3.3 the core sends its own shipping e-mail for every carrier: the module stops
  sending `mail_custom_delivery` while that e-mail is switched on. A new setting, "Keep sending
  the Custom Delivery shipping e-mail", keeps it during a transition. On an older core the module
  sends its message as before.
- The message is sent when an order enters the "sent" status only: saving an order that is
  already sent no longer mails the customer again. The listener runs after the status is written.

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
