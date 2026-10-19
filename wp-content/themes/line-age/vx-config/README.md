# VX config

This directory is the theme's data model. Shader ships it empty.

## Source precedence

VX resolves each object in this order:

1. Database override
2. PHP registration (`vxframework/register` + Blueprint)
3. JSON files in this directory

JSON files here are the default source for generated themes. Prefer one file per object under:

- `post-types/`
- `taxonomies/`
- `fieldsets/`
- `option-pages/`
- `dictionaries/`

## Empty model contract

These directories exist so the theme is valid before any domain exists. Do not add sample post types, fields, or placeholder business data. Create only the model the current site prompt needs.

Write files from `instructions/vx.md`. That file is the complete contract for this theme.
