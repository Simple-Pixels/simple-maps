# Simple Maps

> **Reusable component** · A Google Map that pins every post of a chosen post type, using an ACF Google Map field for the coordinates.

| | |
| --- | --- |
| Type | Reusable component (any site with ACF) |
| Version | 1.1.0 (plugin header; asset versions are hard-coded separately, see [Gotchas](#gotchas)) |
| Main file | `simple-maps.php` |
| Text domain | `sp-map` |
| Prefix | `SP_Map_` (classes), `sp_map_` (options, transients), `sp-map` (handles, CSS) |
| Repository | `Simple-Pixels/simple-maps` |

## What it does

- Adds one shortcode, `[simple_map]` (alias `[sp_map]`), that shows a Google Map with a pin for every published post of a configured post type.
- Pin coordinates come from an **ACF Google Map** field on each post.
- Clicking a pin opens an info window with any of: featured image, title, a 30-word excerpt of the content, and a "Read more →" link to the post.
- The map auto-fits to show every pin (never zooming closer than level 16). Pins at exactly the same coordinates are fanned out in a small circle so they can all be clicked.
- A settings page sets the API key, post type, field name, which info-window parts to show, and the pin colour.
- The API key is also passed to ACF, so the ACF Google Map field works in the editor without extra setup.

## Requirements

| Requirement | Version / notes |
| --- | --- |
| WordPress | No minimum declared. |
| PHP | The settings page says 8.0+. Code is 7.0+ compatible. |
| Required plugins | **Advanced Custom Fields** (free or Pro), with a **Google Map** field. Without ACF the shortcode prints "ACF plugin is required for Simple Maps." |
| External services | **Google Maps JavaScript API** key. Restrict it by HTTP referrer to the site's domains. |

## Installation

1. Deploy the repository root to `wp-content/plugins/simple-maps/`.
2. Activate **Simple Maps**.
3. Create (or pick) a post type for the pins, and an ACF **Google Map** field attached to it.
4. Fill in **Settings → Simple Maps** (below) and save. Saving sets ACF's Google API key, so the field's map picker starts working.
5. Add `[simple_map]` to a page.

## Configuration

**Settings → Simple Maps** (`manage_options`). Stored in the `sp_map_options` option.

| Setting | Key | Default | Notes |
| --- | --- | --- | --- |
| Google Maps API Key | `api_key` | empty | Nothing loads on the front end until this is set. |
| CPT Post Type Slug | `cpt` | `impact_pin` | Any post type, including `post` or `page`. |
| ACF Location Field Name | `location_field` | `location` | The ACF field **name** (not the key). Must be a Google Map field. |
| Show Elements | `show_image`, `show_heading`, `show_content`, `show_readmore` | all on | Controls what appears in the info window. |
| Pin Colour | `pin_color` | `#92ACA0` | Hex only. Invalid values fall back to the default. |

## Usage

```
[simple_map]
[simple_map height="600px" class="my-map"]
```

| Attribute | Default | Notes |
| --- | --- | --- |
| `height` | `420px` | Any CSS length. The map is always 100% wide. |
| `class` | empty | Extra class on the `.sp-map-wrapper` div. |

`[sp_map]` is an alias with the same attributes. Several maps can be on one page; each gets its own ID.

Messages instead of a map:

- "ACF plugin is required for Simple Maps." ACF isn't active.
- "No locations found." No published posts of the post type have the field set.

## How it works

### Data model

| Thing | Stored as |
| --- | --- |
| Settings | `sp_map_options` option |
| Location cache | Transient `sp_map_locations_<md5 of cpt|field>`, 2 hours |
| Relied on, not created | The configured post type and its ACF Google Map field (`lat`, `lng`) |

### Request flow

1. The shortcode calls `get_locations()`, which queries every published post of the configured type that has the field, reads it with `get_field()`, and builds `{ title, lat, lng, url, thumb, content }` for each. The result is cached in the transient.
2. The transient is deleted when a post of the configured type is saved, or when the settings change.
3. The shortcode prints an empty `<div id="sp-map-<uuid>">` and pushes `{ mapId, locations, config }` onto `window.__SP_MAPS__` through `wp_add_inline_script()`.
4. On `acf/init` the API key is pushed into ACF with `acf_update_setting( 'google_api_key', … )`, so the field's map picker works in the editor.

### Endpoints

None.

### Front end

- `sp-map.js` and the Google Maps JavaScript API (`callback=__initSpMaps`) are enqueued on every front-end page once an API key is set.
- `sp-map.js` injects its info-window CSS once and defines `window.__initSpMaps`, which builds every queued map: groups pins by identical coordinates, fans out duplicates (radius 8 to 25 m), adds SVG pin markers, and fits bounds (max zoom 16).

## File structure

```
simple-maps.php          Everything PHP: SP_Map_Simple_Maps class (settings page, sanitising, asset loading,
                         shortcode, location query + transient cache, cache invalidation)
assets/js/
  sp-map.js              Front-end map builder: info-window CSS, pin icons, duplicate fan-out, window.__initSpMaps
  sp-map-admin.js        Colour picker on the settings page
```

## Hooks

The plugin defines no custom hooks. Useful WordPress hooks it relies on:

| Hook | Purpose |
| --- | --- |
| `acf/init` | Passes the API key to ACF. |
| `save_post` | Clears the location cache for the configured post type. |
| `update_option_sp_map_options` | Clears the location cache when settings change. |

## Gotchas

- **Google Maps loads on every front-end page** once an API key is saved, not just pages with the shortcode (`enqueue_frontend()` enqueues it unconditionally). That costs page weight and Maps API loads. Fix by only enqueuing from the shortcode if it matters for a site.
- **Default post type is `impact_pin`**, left over from the site it was first built for. Always set it on a new install.
- **Uses `google.maps.Marker`**, which Google has deprecated in favour of `AdvancedMarkerElement`. It still works, but expect console warnings and plan to migrate.
- **Info-window close button is removed** by deleting `.gm-style-iw-ch` from the DOM. Google changing its markup will break that silently.
- **Map starts centred on Australia** (zoom 4) before fitting bounds. Only noticeable if there are no valid pins.
- **2-hour cache.** Changes to a post made outside `save_post` (direct DB edits, some imports) won't show until the transient expires. Deleting the transient or re-saving settings clears it.
- **Hard-coded strings in JS.** "Read more →" isn't translatable.
- **Asset versions** are hard-coded to `1.0.0` while the plugin is `1.1.0`. Bump them when you change the JS.
- **No `.gitignore` / uninstall routine.** The `sp_map_options` option and transients stay after the plugin is deleted.
- **Overlap with Simple Locations.** [simple-locations](https://github.com/Simple-Pixels/simple-locations) has its own map shortcodes for its `location` post type. Use this plugin when the pins are an arbitrary post type with an ACF Google Map field.
- Code uses spaces and short array syntax, not the WordPress Coding Standards in [CLAUDE.md](CLAUDE.md). Match the existing file for small changes.

## Development

- Coding standards, git workflow and environment notes: [CLAUDE.md](CLAUDE.md).
- Versioning: bump the plugin header and the `wp_register_script()` versions together.
- Testing: create two posts with the same address to check fan-out, toggle each "Show Elements" checkbox, change the pin colour, and confirm a newly published post appears straight away (cache cleared on save).

## Changelog

| Version | Notes |
| --- | --- |
| 1.1.0 | Current version (no earlier history in this repository). |
