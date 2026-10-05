# 4.1.0

- The tracking address is stored where Thelia reads the tracking address of a delivery module
  (module setting `tracking_url`). From Thelia 3.3 the core builds the tracking link of a Custom
  Delivery order from it, for the customer account, the back office, the order API and the
  shipping e-mail. The update moves the existing address there and puts the former global
  setting `custom_delivery_tracking_url` back to its default; nothing has to be typed again.
- The address can be edited on this module's configuration page or, from Thelia 3.3, on the
  shipping page of the module in the back office: both edit the same setting.
- The tracking address is optional and must be an `http(s)` address containing `%ID%` outside the
  host. The tracking number is url-encoded into it. Without a valid address, the module's own
  message gives the bare tracking number, as it did with the former default `%ID%`.
- From Thelia 3.3 the core owns the shipping e-mail of every carrier, switched on or off in the
  store configuration: the module no longer sends `mail_custom_delivery`. A new setting, "Keep
  sending the Custom Delivery shipping e-mail", keeps it during a transition. On an older core the
  module sends its message as before.
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
