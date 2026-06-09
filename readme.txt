=== Review & Rating ===
Contributors: rh-jewel
Tags: reviews, rating, multi criteria rating, custom post type, testimonials
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-criteria review and rating plugin for posts and custom post types.

== Description ==

Review & Rating lets visitors submit multi-criteria reviews for selected WordPress post types. Admins can choose which post types support reviews, configure rating criteria, moderate submissions, and filter reviews by the reviewed post type.

Features:

* Multi-criteria rating form.
* Custom post type support.
* Admin review moderation.
* Admin filtering by reviewed post type.
* Configurable criteria labels.
* Criteria repeater with 1 to 10 rating criteria.
* Optional login requirement.
* Optional one-review-per-email/user rule.
* Optional admin email notification.
* Cached average rating and review count.
* Modern, accessible frontend markup.
* Legacy shortcode compatibility.

== Shortcodes ==

Full review section:

`[review_rating]`

Only rating summary:

`[review_rating_summary]`

Only review form:

`[review_rating_form]`

Only review list:

`[review_rating_list]`

Only review count:

`[review_rating_count]`

Only average rating:

`[review_rating_average]`

Legacy shortcodes are still supported:

`[post_rating]`
`[post_rating_count]`
`[total_post_rating_count]`
`[get_average_rating post_type="tour"]`

Common attributes:

* `post_id` - Review target post ID. Defaults to the current post.
* `show_form` - Show or hide the form. Values: `1` or `0`.
* `show_summary` - Show or hide summary. Values: `1` or `0`.
* `show_reviews` - Show or hide review list. Values: `1` or `0`.
* `limit` - Number of reviews to show. Default: `-1`.

== Changelog ==

= 1.1.0 =
* Refactored plugin to a namespaced PHP OOP architecture.
* Added multi-CPT settings.
* Added admin filtering by reviewed post type.
* Improved review moderation columns.
* Added secure frontend form handling.
* Added configurable criteria settings with a 1 to 10 item repeater.
* Added average/count caching.
* Added modern frontend and admin CSS.
* Preserved legacy shortcode compatibility.

= 1.0.0 =
* Initial release.
