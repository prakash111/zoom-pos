# Server-Driven UI contract

The mobile client renders endpoint-backed screens from schema version `1`.
`GET /api/app/bootstrap` and `GET /api/v1/pos/app/bootstrap` expose:

- `menu_structure`: the recursive drawer definition;
- `screens`: the current tenant's endpoint-backed screen directory;
- `schema_contract`: supported layouts, components, actions, and version;
- `modules`: built-in and active database-registered module metadata.

## Registering a module

Create an `App\Models\SduiModule` row with a unique `slug`, metadata,
`features`, `routes`, and recursive `navigation`. Every leaf navigation item
must have a same-origin `target_endpoint` beginning with `/api/`.

Create one or more `App\Models\SduiScreen` rows for the endpoints referenced by
the navigation. `schema` contains `layout`, `components`, optional `app_bar`,
and optional `fab`. The model validates all component and action types before
saving. Link the module slug in `companies.licensed_modules`; set
`registration_allowed` when it should also be offered during registration.

Example navigation leaf:

```json
{
  "key": "work_queue",
  "title": "Work Queue",
  "icon": "list_alt",
  "type": "link",
  "target_endpoint": "/api/tenant/views/work-queue",
  "permission": "pos"
}
```

Example stored screen schema:

```json
{
  "layout": "scroll_view",
  "components": [
    {
      "type": "text_input",
      "name": "reference",
      "label": "Reference",
      "required": true
    },
    {
      "type": "button_primary",
      "label": "Save",
      "action": {
        "type": "form_submit",
        "endpoint": "/api/tenant/settings/reference",
        "method": "POST",
        "success_toast": "Saved"
      }
    }
  ]
}
```

## Version 1 primitives

Layouts: `container`, `card`, `scroll_view`, `grid_view`, `accordion_group`,
`column`, `row`, and `tabs`.

Display: `text`, `image_network`, `badge`, `icon`, and `divider`.

Inputs: `text_input`, `dropdown_select`, `checkbox`, `toggle_switch`,
`date_time_picker`, `color_picker`, and `step_counter`.

Lists/actions: `line_item_tile`, `table_grid`, `button_primary`,
`button_outlined`, `fab`, and `action_sheet_trigger`.

Actions: `navigate`, `form_submit`, `api_post`, `open_modal`, `navigate_back`,
and `pop`. Network actions accept only `/api/` paths on the configured server
origin. Use `permission` in `module.action` form on stored screens.
