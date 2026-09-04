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
