# SRMS stylesheet guide

Application styles enter through `css/custom.css`. Load it after Bootstrap, the
template, and any plugin stylesheets. Academic workspace pages also load
`css/cbe-academics.css` after it.

## Where changes belong

| File | Purpose |
| --- | --- |
| `css/srms/tokens.css` | Shared colors, spacing, sizes, shadows, and transitions |
| `css/srms/base.css` | Typography, focus indicators, and general utilities |
| `css/srms/layout.css` | Application header, sidebar, content layout, and mobile navigation |
| `css/srms/components.css` | Buttons, form controls, tables, alerts, and dropdown appearance |
| `css/srms/pages.css` | Dashboard, login, public page, and result page variants |
| `css/srms/print.css` | Print layout and visibility |
| `css/cbe-academics.css` | Academic workspace cards, filters, forms, and charts |

## Keep the cascade predictable

- Reuse a shared variable when a value has the same purpose. Form controls,
  Select2, and DataTables use `--control-border`; related buttons and pagination
  use `--primary-border`.
- Place component modifiers beside their component rules. For example,
  `.form-control.input-password-md` must retain its width when the general
  `.form-control` rule sets `width: 100%`.
- Scope page-specific rules to the page's body class. The public pages use
  Bootstrap 5; the application pages use Bootstrap 3.
- Keep application navigation changes at the shared 992px breakpoint. General
  form and table adjustments use 768px; narrower header adjustments use 576px.
- Prefer a targeted selector to another `!important`. Existing necessary
  overrides explain which template, utility, or plugin rule they replace.
- Keep vendor stylesheets and generated template CSS unchanged. Add application
  overrides to the files above. The legacy Sass build does not compile these
  plain CSS files.

## Check a styling change

Preview the affected page at desktop, tablet, and phone widths, including 320px
and either side of any breakpoint you change. For navigation changes, check the
sidebar toggle, account menu, notifications, and keyboard focus. For table
changes, check horizontal scrolling and filter controls. Check print preview
when changing content widths or margins.

Static HTML previews can verify styling without a database. Use the running PHP
application to check authenticated pages, live records, and complete workflows.
