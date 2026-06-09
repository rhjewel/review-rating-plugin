# Review & Rating Plugin Upgrade Roadmap

## Goal

Upgrade this plugin into a modern, maintainable, PHP OOP WordPress plugin for multi-criteria reviews and ratings.

The plugin must remain a multi-criteria review system. It should support multiple custom post types, provide admin filtering by reviewed post type, and follow current WordPress coding, security, accessibility, and internationalization standards. Also must have existing features.

## Core Requirements

- Keep multi-criteria rating support.
- Support reviews for multiple public custom post types.
- Add admin panel filtering by reviewed post type.
- Use clean PHP OOP architecture.
- Follow WordPress Coding Standards.
- Sanitize all input, validate all submitted data, and escape all output.
- Make frontend UI modern, accessible, and theme-friendly.
- Avoid hard dependency on one theme, Bootstrap, or Bootstrap Icons unless explicitly configured.
- Keep review approval/moderation workflow.

## Recommended Plugin Structure

```text
review-rating-plugin/
├── assets/
│   ├── css/
│   │   └── review-rating.css
│   └── js/
│       └── review-rating.js
├── includes/
│   ├── Admin/
│   │   ├── Review_List_Table.php
│   │   └── Settings_Page.php
│   ├── Frontend/
│   │   ├── Assets.php
│   │   ├── Form_Handler.php
│   │   └── Shortcodes.php
│   ├── Models/
│   │   └── Review.php
│   ├── Repositories/
│   │   └── Review_Repository.php
│   ├── Services/
│   │   ├── Rating_Calculator.php
│   │   ├── Review_Moderation.php
│   │   └── Schema_Markup.php
│   ├── CPT.php
│   ├── Plugin.php
│   └── Settings.php
├── languages/
│   └── review-rating.pot
├── templates/
│   ├── review-form.php
│   ├── review-list.php
│   └── rating-summary.php
├── readme.txt
├── uninstall.php
└── review-rating.php
```

## OOP Coding Standard

Use namespaced classes instead of global generic class names.

Recommended namespace:

```php
namespace ReviewRating;
```

Example bootstrap flow:

```php
final class Plugin {
    public function boot(): void {
        ( new CPT() )->register_hooks();
        ( new Settings() )->register_hooks();
        ( new Frontend\Shortcodes() )->register_hooks();
        ( new Frontend\Form_Handler() )->register_hooks();
        ( new Admin\Settings_Page() )->register_hooks();
    }
}
```

Requirements:

- Prefix all hooks, option names, meta keys, and shortcodes.
- Avoid generic class names like `Review_Main`.
- Keep one responsibility per class.
- Avoid duplicated rating calculation logic.
- Use typed properties and return types where compatible with the plugin's minimum PHP version.
- Add inline documentation only where it clarifies behavior.

## Main Plugin File Improvements

The main plugin file should only:

- Define plugin headers.
- Define constants.
- Load the autoloader or required files.
- Register activation/deactivation hooks.
- Boot the main plugin class.

Recommended constants:

```php
define( 'REVIEW_RATING_VERSION', '1.1.0' );
define( 'REVIEW_RATING_FILE', __FILE__ );
define( 'REVIEW_RATING_PATH', plugin_dir_path( __FILE__ ) );
define( 'REVIEW_RATING_URL', plugin_dir_url( __FILE__ ) );
define( 'REVIEW_RATING_BASENAME', plugin_basename( __FILE__ ) );
```

## Custom Post Type

The review CPT should be an admin-managed data type, not a public content archive.

Recommended CPT behavior:

- `public => false`
- `show_ui => true`
- `show_in_menu => true`
- `publicly_queryable => false`
- `has_archive => false`
- `exclude_from_search => true`
- `show_in_rest => false` unless REST support is intentionally added
- `supports => array( 'title', 'editor' )`
- `menu_icon => 'dashicons-star-filled'`

The CPT should store:

- Reviewer name.
- Reviewer email, optional.
- Review content.
- Reviewed post ID.
- Reviewed post type.
- Rating values for each criterion.
- Calculated average rating.
- Review status.

Recommended meta keys:

```text
_review_rating_post_id
_review_rating_post_type
_review_rating_reviewer_name
_review_rating_reviewer_email
_review_rating_criteria
_review_rating_average
```

Register meta with `register_post_meta()` and include sanitization/auth callbacks.

## Multi-CPT Support

Admin settings should allow selecting which post types can receive reviews.

Recommended behavior:

- Get public post types with `get_post_types()`.
- Exclude attachments and the review CPT.
- Let admin enable/disable review support per post type.
- Store enabled post types in an option.
- Validate submitted review post ID against enabled post types.
- Add shortcode rendering only for enabled post types.

Recommended setting:

```text
review_rating_enabled_post_types
```

## Admin Filtering

The review list screen should include filters for:

- Reviewed post type.
- Review status.
- Rating value.
- Date.

Minimum required filter:

- Post type based filter.

Important implementation detail:

Store the reviewed post type directly in review meta. Do not fetch all posts of a post type and build a large `IN` query.

Recommended filter query:

```php
$query->set(
    'meta_query',
    array(
        array(
            'key'   => '_review_rating_post_type',
            'value' => $selected_post_type,
        ),
    )
);
```

Admin columns should include:

- Reviewer.
- Rating average.
- Reviewed post.
- Reviewed post type.
- Review status.
- Submitted date.
- Approve/unapprove action.

## Settings Page

Use the WordPress Settings API properly.

Settings should include:

- Enabled post types.
- Criteria labels.
- Criteria count or configurable criteria list.
- Require login to review.
- Require approval before display.
- One review per user/post.
- Show rating summary.
- Show review form.
- Enable schema markup.
- Enable email notification.
- Admin notification email.

Every setting must have a sanitize callback.

Example:

```php
register_setting(
    'review_rating_settings',
    'review_rating_settings',
    array(
        'sanitize_callback' => array( $this, 'sanitize_settings' ),
        'default'           => Settings::defaults(),
    )
);
```

## Frontend Review Form

The review form should:

- Use nonce protection.
- Use accessible rating controls.
- Use proper labels.
- Support keyboard interaction.
- Validate required criteria.
- Validate rating range from 1 to 5.
- Validate reviewed post ID.
- Show success/error notices.
- Prevent duplicate reviews if enabled.
- Include spam protection.

Recommended fields:

- Reviewer name.
- Reviewer email.
- Review content.
- Multi-criteria ratings.
- Honeypot field.
- Nonce field.
- Reviewed post ID.

Do not rely on theme-specific classes like `primary-btn1`.

## Rating Criteria

Criteria should be configurable instead of hardcoded only as:

```text
overall
transport
food
hospitality
destination
```

Recommended default criteria:

```php
array(
    'overall'     => __( 'Overall', 'review-rating' ),
    'service'     => __( 'Service', 'review-rating' ),
    'value'       => __( 'Value', 'review-rating' ),
    'experience'  => __( 'Experience', 'review-rating' ),
)
```

For travel/tour websites, optional suggested criteria:

```text
transport
food
hospitality
destination
guide
value
```

Each criterion should have:

- Stable key.
- Editable label.
- Enabled/disabled state.
- Sort order.

## Rating Calculation

Create a dedicated `Rating_Calculator` service.

Responsibilities:

- Calculate average for one review.
- Calculate average for one post.
- Calculate criterion averages for one post.
- Calculate total review count.
- Recalculate cached values after review status changes.

Cache calculated values on the reviewed post:

```text
_review_rating_average
_review_rating_count
_review_rating_criteria_average
```

This avoids expensive repeated `get_posts( -1 )` calls on every page load.

## Shortcodes

Keep current shortcode behavior but standardize the API.

Recommended shortcodes:

```text
[review_rating]
[review_rating_summary]
[review_rating_form]
[review_rating_list]
[review_rating_count]
[review_rating_average]
```

Shortcode attributes should support:

```text
post_id
post_type
show_form
show_summary
show_reviews
limit
orderby
order
```

All shortcode attributes must be parsed with `shortcode_atts()`.

## Modern Frontend UI

The plugin UI should be clean, neutral, and reusable across themes.

Frontend components:

- Rating summary.
- Criteria progress bars.
- Review list.
- Review form.
- Star rating input.
- Success/error notice.

Design requirements:

- Responsive layout.
- Accessible focus styles.
- No theme-specific button classes.
- No Bootstrap dependency unless admin setting enables it.
- CSS class prefix: `rrp-`.
- CSS variables for theme customization.

Example CSS variable approach:

```css
:root {
    --rrp-accent: #f5a623;
    --rrp-text: #1f2933;
    --rrp-muted: #6b7280;
    --rrp-border: #d8dee4;
}
```

## JavaScript

JavaScript should be dependency-light and accessible.

Requirements:

- Remove unnecessary jQuery dependency if not needed.
- Support keyboard rating selection.
- Update hidden input safely.
- Work with multiple forms on one page.
- Avoid duplicate modal IDs.
- Do not assume Bootstrap modal is available.

Use `wp_enqueue_script()` with dependency/version management.

## Asset Loading

Do not enqueue assets globally on every frontend page.

Recommended behavior:

- Enqueue frontend CSS/JS only when a review shortcode is rendered.
- Use plugin version or `filemtime()` for local development cache busting.
- Enqueue admin CSS/JS only on plugin admin screens.

## Security Checklist

- Check nonce before processing form submission.
- Use `wp_unslash()` for request data.
- Use `sanitize_text_field()` for names and simple text.
- Use `sanitize_email()` for email.
- Use `sanitize_textarea_field()` or `wp_kses_post()` for review content.
- Use `absint()` for IDs.
- Use bounded integer validation for ratings.
- Validate reviewed post exists.
- Validate reviewed post type is enabled.
- Escape output using `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.
- Check user capability for admin actions.
- Use `wp_safe_redirect()`.
- Never trust hidden form fields.

## Internationalization

Requirements:

- Keep text domain `review-rating`.
- Make all visible strings translatable.
- Use escaped translation helpers:
  - `esc_html__()`
  - `esc_html_e()`
  - `esc_attr__()`
  - `esc_attr_e()`
- Update `languages/review-rating.pot`.
- Avoid hardcoded untranslatable labels in PHP, HTML, and JS.

## Accessibility

Requirements:

- Rating control must work with keyboard.
- Use semantic form labels.
- Use `aria-live` for success/error messages.
- Provide visible focus styles.
- Avoid icon-only controls without accessible labels.
- Ensure color contrast meets WCAG AA.
- Avoid using only color to communicate rating state.

## Moderation Workflow

Admin should be able to:

- Approve review.
- Unapprove review.
- Delete review.
- View target post.
- Filter by reviewed post type.
- Filter by approval status.
- Filter by rating.

Optional:

- Bulk approve/unapprove.
- Email admin when a new review is submitted.
- Email reviewer when review is approved.

## Schema Markup

Add optional JSON-LD schema support.

Important:

- Only output schema on valid single reviewed posts.
- Only output aggregate rating when there is at least one approved review.
- Make schema optional from settings.
- Do not output misleading review schema for unsupported post types.

## Documentation

Add or improve:

- `readme.txt` for WordPress.org format.
- Installation instructions.
- Shortcode documentation.
- Settings documentation.
- Developer hooks documentation.
- Changelog.
- Upgrade notes.

Recommended hooks:

```text
review_rating_before_form
review_rating_after_form
review_rating_before_review_insert
review_rating_after_review_insert
review_rating_criteria
review_rating_enabled_post_types
review_rating_average
```

## Testing

Add tests/checks for:

- Form submission validation.
- Rating range validation.
- Enabled post type validation.
- Average rating calculation.
- Admin post type filter.
- Shortcode attribute handling.
- Escaping and sanitization.

Recommended tooling:

- PHPCS with WordPress Coding Standards.
- PHPStan or Psalm for static analysis.
- ESLint/Prettier for JavaScript.

## Upgrade Priority

1. Refactor bootstrap and class structure into PHP OOP.
2. Secure form handling and request validation.
3. Make review CPT private and admin-focused.
4. Add proper settings with sanitize callbacks.
5. Add multi-CPT settings and validation.
6. Store reviewed post type meta and improve admin filtering.
7. Extract rating calculation into a service.
8. Cache rating average/count on reviewed posts.
9. Modernize frontend markup, CSS, and JS.
10. Add accessibility and internationalization improvements.
11. Add schema markup, docs, and quality tooling.

## Final Expected Result

After the upgrade, this plugin should be a reusable, modern WordPress review plugin that can be attached to multiple custom post types, displays multi-criteria ratings with a clean frontend UI, gives admins strong filtering/moderation tools, and follows current WordPress standards for PHP OOP, security, performance, accessibility, and maintainability.
