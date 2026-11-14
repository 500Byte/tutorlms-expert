# Tutor LMS feature surface

> **Read when:** you need to confirm whether a Tutor capability is native, Pro, addon or third-party before scoping.
>
> Verified 2026-11-13 against the official docs index and these pages: native-ecommerce/native-ecommerce-overview, subscriptions/fundamentals, memberships/configuration, gift-course/setup, ai-studio/course-creation, certificate-builder/installation-2, learning-experience/learning-experience-live-classes, addons/content-bank, plus the docs sidebar for menus and addon names. Anything not listed here is unverified — check the install, don't assume.

The most common scoping error is calling a Tutor feature "not native" because it is not in the free core. Tutor ships four tiers:

| Tier | What it means |
|---|---|
| Core | Ships with Tutor LMS: course builder, lessons, quizzes, Q&A, announcements, reviews, notes, discussions, calendar UI, Kids Mode, Gift Course toggle, AI Studio UI, REST auth (4.2+) |
| Pro addon | Toggle under **Tutor LMS Pro > Addons**: Subscriptions, Memberships, Content Bank, Assignments, Gradebook, Reports, H5P, Calendar, Notifications, Multi Instructors, Course Preview, Attachments, Enrollment, Social Login, BuddyPress, Zoom, Google Meet, Google Classroom, WPML, Bundles, Content Drip, Prerequisites |
| Separate plugin | Downloaded and installed like any plugin: **Tutor LMS Certificate Builder** (the Certificate addon must also be enabled) |
| External engine / credential | Commerce engine: WooCommerce, Woo Subscriptions, EDD, Paid Memberships Pro, Kadence Memberships, BunnyNet. Credential only: AI provider key, Zoom/Meet OAuth |

> **Free vs Pro:** the docs do not label every core screen with its license tier, and availability changes between releases. "Core" means it is part of the Tutor product, not that the free plugin includes it. Confirm the license and the addon row in the install before quoting.

## Decision rules

1. **One commerce engine per site.** `Tutor LMS > Settings > Monetization > Select eCommerce Engine` is a single choice (Native, WooCommerce, EDD, …). Native Subscriptions and Memberships assume **Native**; a site already on WooCommerce can use the **Woo Subscriptions addon** instead — do not migrate engines for that. When switching to Native, regenerate pages in **Tutor LMS > Tools > Tutor Pages**.
2. **A toggle is not a working feature.** Subscriptions need a paid course and a plan; live classes need Zoom or Meet credentials; AI Studio needs a connector key; certificates need a template assigned; H5P needs content.
3. **Do not promise what is not in this file.** Cohorts, SCORM/xAPI players, affiliate/referral, SMS, CRM and per-course grade weighting are not documented Tutor features. Scope them as custom work or an external plugin.
4. **Feature requests map to data.** Every "can it..." answer ends with the post/meta/option that stores it, so it can be verified. See `data-model.md`.

## Creation

| Feature | Tier | Where | Depends on / stores | Gotcha |
|---|---|---|---|---|
| Course Builder (Tutor 4) | Core | Tutor LMS > Courses; Course Basics + Curriculum | `courses`/`topics`/`lesson`/`tutor_quiz`/`tutor_assignments` posts | Curriculum order is `menu_order`; duplicate orders break prev/next |
| Content Bank | Pro addon | Tutor LMS Pro > Content Bank | Collections of reusable lessons, assignments, quiz questions; import from courses; export/import `.json` | Assignments also need the Assignment addon; content is linked, not copied |
| AI Studio | Course Builder UI + AI connector key | Course Builder: "Generate with AI" and field icons | Prompt → outline (topics/lessons/assignments/quizzes), title, description, featured image (styles, Magic Fill); separate Quiz Creation flow | It drafts, you own accuracy; nothing is auto-published |
| Certificate Builder | Certificate addon + separate plugin | Enable Certificate addon; install `Tutor LMS Certificate Builder` | Templates/elements/layers; assign per course via `tutor_course_certificate_template` | Verify which builder the install uses; addon off = no certificate menu |

## Engagement

| Feature | Tier | Where | Depends on / stores | Gotcha |
|---|---|---|---|---|
| Lessons (video/reading) | Core | Curriculum | `_video`, content HTML | Large video belongs on YouTube/Vimeo/Bunny, not shared hosting |
| Resources / attachments | Pro addon | Lesson settings | `_tutor_attachments` (IDs) | Tab empty until IDs are attached |
| Course preview | Pro addon | Lesson settings | `_is_preview = yes` | Use one strong lesson per course as the ad hook |
| Quizzes | Core | Curriculum | `tutor_quiz_option`; questions/answers in `tutor_quiz_questions` / `tutor_quiz_question_answers` | 13 documented types: image marking, range, pin, graph, puzzle, true/false, multiple choice, open ended, fill in the blanks, short answer, matching, image answering, ordering |
| Assignments | Pro addon | Curriculum | `assignment_option`, `_tutor_course_id_for_assignments`, total/pass mark | Student must be enrolled in the assignment's course |
| Live Classes | Core screen + addon | Curriculum lesson item; student sees countdown, dates, host email, Join/Skip | **Requires Zoom or Google Meet addon + credentials** | Ended sessions show an expired state; there is no built-in meeting server |
| Q&A | Core | Per course | `_tutor_enable_qa` | Per-course, not global |
| Announcements | Core | Course + instructor dashboard | `tutor_announcements` posts | Not the same as Notifications |
| Reviews | Core | Course page + admin approval | comments/ratings | Moderate before they show |
| Gradebook | Pro addon | Tutor LMS Pro > Gradebook | Global `gradebook_scale`; quiz attempts + assignment marks | No native per-course 40/60 weighting |
| Reports | Pro addon | Tutor LMS Pro > Reports | Revenue/course/student aggregates | Useful after the first cohort, not before |
| H5P | Pro addon | Addons list | H5P content embedded in lessons | Gradebook linkage is **not documented**; verify in the install before promising scores |
| Calendar | Pro addon | Learner dashboard + instructor | Live classes, assignment deadlines | UI exists in core docs; the addon is what fills it |
| Notifications | Pro addon | Bell + settings | In-app notifications | Email is a separate addon |
| Discussions / Notes | Core | Learner and instructor dashboards | Per-course threads / private notes | Notes are student-private |
| Kids Mode | Core | Learner dashboard | Hides account areas for minors | Test with a real child account |
| Multi Instructors | Pro addon | Course editor + Instructors menu | Extra instructor IDs per course | Free is single author-instructor |

## Monetization

| Feature | Tier | Where | Depends on / stores | Gotcha |
|---|---|---|---|---|
| Native eCommerce | Core | Settings > Monetization > engine = Native | Cart/Checkout pages, currency/decimals, coupon toggle, Buy Now, guest checkout | Must regenerate Tutor pages after switching engine |
| Payment gateways (Native engine) | Native | Settings > Monetization > Payment Gateways | PayPal, Stripe, Paddle, Authorize.net, Paystack, Mollie, Klarna, Alipay, Razorpay, 2Checkout, Manual | With the Woo engine, gateways, coupons, taxes and orders live in WooCommerce instead |
| Coupons / Taxes / Orders | Native | Settings > Monetization, Orders menu | Coupon codes and automatic discounts; regional tax | Not shared with WooCommerce |
| Subscriptions | Pro addon | Enable in Addons; per-course Pricing Model > Paid > Add Subscription; manage in Tutor LMS Pro > Subscriptions | Native engine; plan name, price, billing interval/cycle ("Until cancelled"), enrollment fee, certificate opt-out, featured, sale price | Purchase Options can be subscriptions only, one-time only, or both; statuses pending/active/on hold/cancelled. Existing Woo sites can use the Woo Subscriptions addon instead |
| Memberships | Pro addon (same Subscriptions addon) | Tutor LMS Pro > Settings > Subscriptions | Native engine; full-site or category plans, trial, skip payment for free trials, sale, featured; `[tutor_membership_pricing]` shortcode; Membership-Only Mode | Membership-Only Mode removes individual course sales site-wide. PMPro/RCP integrations exist as separate addons — never run two access models |
| Gift Course | Core toggle | Settings > Course > Enable Course Gifting (no Pro addon per docs) | Recipient name/email, optional delivery date and message; gifts courses **and bundles**; auto-creates recipient account | Refund only before "Reveal Gift"; emails need the Email addon |
| Course Bundle | Pro addon | Course Bundles | `course-bundle` post + `bundle-course-ids` + product | Price is derived from courses (manual price possible); **enrollment fires on `completed`, not `processing`** |
| Manual enrollment | Pro addon | Enrollment | Enrollment posts `tutor_enrolled` | Use for comps/cohorts, not for sales |
| PMPro / RCP / Kadence Memberships | External integration | Enable the matching addon; configure in that plugin | Membership level grants access; Tutor maps it | Do not combine with native Memberships or membership-only mode |
| Prerequisites / Content Drip | Pro addons | Course settings / lesson release | `_tutor_course_prerequisites_ids`, `enable_content_drip` | Prefer recommendation over hard gate |

## Integrations and developer surface

- **Builders:** Elementor widgets (course title, author, price, curriculum, reviews, bundles, wishlist…), Divi modules, Oxygen elements.
- **Classroom/live:** Zoom, Google Meet, Google Classroom addons.
- **Identity/community:** Social Login (Google/Facebook), WPML, Loco Translate, BuddyPress.
- **Media:** BunnyNet/Bunny Stream.
- **Commerce plugins:** WooCommerce, Woo Subscriptions, EDD, PMPro, Kadence Memberships.
- **Migration:** LearnDash, LifterLMS, LearnPress, WooCommerce → Native eCommerce.
- **Developer:** action/filter hooks, custom payment gateway, custom course-builder fields, REST API (`rest-api.md`) with free read routes and Pro write routes.

## Admin surface (from the docs sidebar)

Menus: Courses, Categories, Tags, Students, Instructors, Announcement, Q&A, Quiz Attempts, Themes, Tools (REST API, Tutor Pages), plus Pro-only Content Bank/Subscriptions/Reports. Settings open under **Tutor LMS > Settings** for core panels and **Tutor LMS Pro > Settings** for Pro panels (Subscriptions/Memberships); the docs use both roots. Settings tabs: General, Course, Monetization, Design, Advanced, Legal Consents, Grading, Email, Notifications, Authentication, Certificate, Preferences, License Activation. Confirm the actual menu in the install — the sidebar describes the latest release.

## Verify in the install, not in the docs

```bash
wp plugin list --status=active | grep -i tutor
wp option get tutor_addons_config --format=json | jq 'to_entries[] | select(.value.is_enable==1) | .key'
wp option get tutor_option --format=json | jq 'keys'
```

Confirm the addon row, the menu item, and the option key before telling a client "Tutor does that". The docs describe the latest release; a client site may run 3.x without the V4 feature.
