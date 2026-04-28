# Technical Specification: lang2polylang Plugin

## Context

The goal is to automate the translation linking in Polylang for a WordPress site that previously managed languages using URL suffixes.

## Logic Requirements

1. **Source/Target Pattern:** - English (Base): `domain.com/path/`
   - Spanish (Target): `domain.com/path-es/`
2. **Post Type:** Restricted to 'page' by default, but should be extensible.
3. **Polylang Integration:**
   - Use `pll_set_post_language(id, lang)` to ensure IDs are assigned to 'en' and 'es'.
   - Use `pll_save_post_translations(array)` to link the IDs in the `term_relationships` table via Polylang API.
4. **Execution Flow:**
   - Triggered manually via an Admin Page under 'Tools'.
   - Use `WP_Query` with `name__like` or regex to identify candidate pages.

## Code Structure (Current Blueprint)

- **File:** `lang2polylang.php`
- **Main Hook:** `admin_menu` for the UI.
- **Key Functions:**
  - `l2p_run_linking()`: The core loop. It parses slugs, removes the `-es` suffix, finds the parent ID, and executes the Polylang API calls.
  - `l2p_check_polylang()`: Dependency check.

## Desired Enhancements for Future Iterations

- Implement AJAX or Batch Processing for sites with >500 pages to avoid timeouts.
- Add support for custom post types (CPTs).
- Add a "Dry Run" mode to preview links before saving.
