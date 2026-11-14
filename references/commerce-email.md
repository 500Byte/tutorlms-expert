# Commerce and email: selling courses with Tutor LMS + WooCommerce

> **Read when:** a paid course gives no access or transactional email never arrives after checkout.

> Money lives in WooCommerce. Access lives in Tutor. Almost every "paid but no access" or "email never arrived" ticket is those two systems disagreeing about a status.
>
> Verified against Tutor 4.x + Tutor Pro 4.x (`course-bundle`, `wc-subscriptions`, `tutor-email` addons), WooCommerce 11.1.2, WooCommerce Stripe Gateway 11.0.0 and FluentSMTP 2.4.5, read from plugin source on 2026-11-13. Version-specific behaviour is marked inline — check the installed version before repeating it.

## 0. Who owns what

| Concern | Owner | Where the truth lives |
|---|---|---|
| Price charged, currency, tax, coupons | WooCommerce | `WC_Product`, `WC_Coupon`, `woocommerce_*` options |
| Order, payment, refund, receipt email | WooCommerce | `WC_Order`, `WC_Email_*` |
| Course access, progress, certificate | Tutor | `tutor_enrolled` post, `tutor_after_enrolled` |
| Learning emails (enrolled, completed, graded, reminder) | Tutor Pro `tutor-email` addon | `tutor_option['email_to_students']` |
| Cart-abandonment email | WooCommerce (11.0–11.1 only) or a plugin | see §9 |

The switch that enables all of this is `monetize_by = wc`. With anything else (`free`, Tutor's native engine, EDD), the rest of this document does not apply.

```bash
wp eval 'echo tutor_utils()->get_option("monetize_by");'   # must print: wc
wp eval 'echo tutor_utils()->has_wc() ? "wc loaded" : "no wc";'
```

## 1. The purchase chain, in order

This is the model to hold in your head. Every failure in §13 is a break at one of these steps.

1. Course is `_tutor_course_price_type = paid` and has `_tutor_course_product_id` → `tutor_utils()->is_course_purchasable()` returns true. Without both, the price and the buy button do not render at all. (Exception: a `course-bundle` with a linked product is purchasable regardless of price type.)
2. The WooCommerce product holds the money: price, sale schedule, tax class, stock.
3. Add to cart. Tutor's button posts `add-to-cart=<product_id>`; the customer lands in WooCommerce's cart, not Tutor's.
4. Order item created:
   - classic checkout → `woocommerce_new_order_item`
   - block checkout (Store API) → the order is parked as `checkout-draft`, and Tutor reacts on `woocommerce_order_status_changed` when it leaves that status
5. Either path calls `EnrollmentModel::do_enroll( $course_id, $order_id, $customer_id )`. Because the course is purchasable, the `tutor_enrolled` post is created with **`post_status = pending`**.
6. Payment completes → order moves to `processing` or `completed`. Which one is decided by WooCommerce, not Tutor:
   `woocommerce_payment_complete_order_status` returns `completed` only when `$order->needs_processing()` is false, i.e. every line item is **virtual *and* downloadable**.
7. `woocommerce_order_status_changed` → `Tutor\WooCommerce::enrolled_courses_status_change()` writes the new status onto the enrollment post — or forces `completed` when `tutor_woocommerce_order_auto_complete = on`.
8. `tutor_after_enrolled( $course_id, $user_id, $enrolled_id )` fires **only when the transition target is `completed`**. That hook is what sends Tutor's "Course Enrolled" email.
9. Access is granted only when the enrollment is `completed`: `EnrollmentModel::is_enrolled()` defaults to `$is_complete = true`.

### The trap in step 6+7

`Tutor\Course::sync_course_with_wc_product()` sets `_virtual = yes` on the product. It does **not** set `_downloadable`. A virtual-but-not-downloadable order still needs processing, so it lands in `processing`; with `tutor_woocommerce_order_auto_complete` off (the default), the enrollment is written as `processing`, and `is_enrolled()` returns false. Result: the card was charged, the student sees "Enroll", and nothing looks broken in the logs.

Pick one fix and state it in the handover:

- enable **Tutor LMS → Settings → Monetization → Automatically Complete WooCommerce Orders** (`tutor_woocommerce_order_auto_complete = on`), or
- mark the product **Virtual + Downloadable** so WooCommerce completes the order on payment, or
- accept `processing` and complete orders manually — then never promise instant access.

Manual payments (`cod`, `cheque`, `bacs`) are excluded from auto-complete by design, and `mark_order_complete()` refuses to run in admin.

## 2. Product per course

| Key | On | Meaning |
|---|---|---|
| `_tutor_course_product_id` | course | The link. Tutor resolves product↔course by this meta (`product_belongs_with_course()`), in both directions. |
| `_tutor_product` | product | Marker set by Tutor ("For Tutor" checkbox). Informational; it does not create the link. |
| `_tutor_course_price_type` | course | `paid` / `free`. Only `paid` makes the course purchasable. |
| `_virtual` | product | Set to `yes` by Tutor. |
| `tutor_course_price`, `tutor_course_sale_price` | course | **Native-engine only.** With `monetize_by = wc` the product price wins. Do not "fix" a price here. The underscore-prefixed `_tutor_course_price` / `_tutor_course_sale_price` seen in old snippets are not read by Tutor 4. |

Product type: **Simple**. Tutor's own creator is `\TUTOR\Course::create_wc_product( $title, $reg_price, $sale_price, $product_id = 0, $status = 'publish' )`, which also sets `sold_individually = true`. Then link it:

```php
// Idempotent: re-running only re-asserts the link and the virtual flag.
\TUTOR\Course::sync_course_with_wc_product( $course_id, $product_id );
```

Do not hand-roll `update_post_meta( $course_id, '_tutor_course_product_id', ... )` unless you also replicate the `_virtual` and `_tutor_product` markers — a product without them sells fine but behaves like a shippable item everywhere else.

### Verify the link

```bash
# Course side
wp post meta get <course_id> _tutor_course_product_id
wp eval 'var_export(tutor_utils()->is_course_purchasable(<course_id>));'

# Product side, through Tutor's own resolver
wp eval 'var_export(tutor_utils()->product_belongs_with_course(<product_id>));'

# Price Tutor will render (comes from the product)
wp eval '$p=wc_get_product(<product_id>); echo $p ? $p->get_price_html() : "no product";'
```

## 3. Price, currency, tax

- Render money with WooCommerce's API: `wc_price()`, `$product->get_price_html()`, `wc_get_price_including_tax()` / `wc_get_price_excluding_tax()`. Tutor already does this once `monetize_by = wc`; a custom template that prints `$course->price . '$'` will drift from the cart.
- **Currency comes from WooCommerce** (`woocommerce_currency`), not from Tutor's currency settings. `tutor_utils()->currency_symbol()` returns `get_woocommerce_currency_symbol()` under WC. A mismatch between the course page and the cart means someone edited the Tutor setting and expected it to win.
- **Tax comes from WooCommerce.** `woocommerce_calc_taxes`, `woocommerce_prices_include_tax`, `woocommerce_tax_display_shop`, and the standard/reduced/zero tax classes. Set the tax class on the product, not on the course.
- Taxability of online courses varies by US state and by nexus. This is a client + accountant decision; do not infer it from another store. What you can verify is that the number the customer sees equals the number charged:

```bash
wp eval '$o=wc_get_order(<order_id>); echo $o->get_total();'
# compare with: subtotal + shipping + tax - discount, from the order object, never from floats you computed yourself.
```

- A sale price with a schedule lives on the product (`_sale_price`, `_sale_price_dates_from/_to`). Copying the discounted number into the course meta produces a page that advertises a price the cart will not honour.

## 4. Checkout

Tutor works with both WooCommerce checkouts. Know which one the site runs before debugging:

```bash
wp eval '$p=get_page_by_path("checkout"); echo $p ? ( has_block("woocommerce/checkout", $p) ? "block" : "classic/shortcode" ) : "no checkout page";'
```

| | Classic (`[woocommerce_checkout]`) | Block (`woocommerce/checkout`) |
|---|---|---|
| Order is created | at submit | as `checkout-draft` while the shopper is mid-flow |
| Tutor enrolls on | `woocommerce_new_order_item` | status change out of `checkout-draft` |
| Duplicate-enrollment guard | `tutor_wc_should_process_checkout_order_item` | same |

Rules:

- Cart, checkout, and my-account must be excluded from full-page cache and CDN HTML cache. A cached cart shows one customer another customer's items.
- Required pages: Cart, Checkout, My account (WooCommerce → Settings → Advanced), plus Tutor's dashboard page containing `[tutor_dashboard]`.
- **Guest mode** is opt-in: `tutor_utils()->get_option('enable_guest_course_cart')`, default `off`. Off means the buy button opens a login modal. If guest checkout is on, Tutor stores the guest customer id on the course (`_tutor_wc_guest_customer_id`) and enrolls on `woocommerce_guest_session_to_user_id`. Guest orders still need a real billing email or the customer can never be emailed or recover the order.
- Never test checkout only in admin. Admin-created orders skip several of these paths (`is_admin()` guards in auto-complete and `mark_order_complete`).

## 5. Payments, refunds, reconciliation

### Stripe (WooCommerce Stripe Gateway 11.0.0)

| Item | Value |
|---|---|
| Settings option | `woocommerce_stripe_settings` |
| Test/live switch | `testmode` = `yes`/`no` (`WC_Stripe_Mode::is_test()`) |
| Webhook endpoint | `https://<site>/?wc-api=wc_stripe` |
| Webhook secret | `webhook_secret` (live) / `test_webhook_secret` (test) |
| Signature check | `STRIPE-SIGNATURE` header (`t=<ts>,v1=<sig>`); HMAC-SHA256 over `<timestamp>.<raw body>`, 5-minute timestamp tolerance; a mismatch returns HTTP 400 |
| Log | WooCommerce → Status → Logs, source `woocommerce-gateway-stripe` (`WC_Stripe_Logger`) |
| Useful action | `wc_stripe_webhook_received( $type, $notification, $order )` |

The API keys are stored in that option — that is how the official plugin works. Protect it: restrict `manage_woocommerce`, never export the option in a migration, never commit a dump, and never copy a live settings row onto staging.

Order status must not depend on the customer's browser returning to the thank-you page. Confirm the webhook is registered in the Stripe dashboard, in the same mode as the keys, and that recent deliveries return 2xx.

### PayPal

Same principles: credentials per mode, webhook/IPN registered against the site URL, refunds issued from WooCommerce. A refund created only in the PayPal dashboard leaves the WooCommerce order untouched.

### Refunds

WooCommerce drives this. When the order is fully refunded, `wc_order_fully_refunded()` sets the parent order to `refunded` (filterable via `woocommerce_order_fully_refunded_status`). That status change reaches Tutor and the enrollment stops being `completed`, so access is revoked. A partial refund leaves the order status alone, so access stays.

Practical consequences:

- Refund from **WooCommerce → Orders**, not from the gateway dashboard. The gateway dashboard moves money; only the WooCommerce refund moves the order, the email, and the enrollment.
- A refund is not a delete. Keep the order; it is the audit trail. If you must remove access without money moving, set the enrollment to a non-`completed` status and note why: `EnrollmentModel::update_enrollments( EnrollmentModel::STATUS_CANCEL, [ $enrolled_id ] )` (the constant is `'cancel'`, not `'cancelled'`; `is_enrolled()` requires `completed`, so anything else removes access).
- Tutor's earnings deduct `refund_amount` and `coupon_amount`; instructor payouts must be checked after any refund.

### Reconciliation

Match `$order->get_transaction_id()` (or `$order->get_meta('_stripe_charge_id')`) against the gateway's payout report, per day. Discrepancies are usually: a capture that never reached WooCommerce (webhook 4xx), a refund issued only at the gateway, or two orders for one charge after a retried checkout. Surface them weekly; silent gaps in commerce are revenue leaking.

## 6. Bundles

| Item | Value |
|---|---|
| Post type | `course-bundle` |
| Courses | `bundle-course-ids` (comma-separated string) |
| Price | Calculated: `BundleModel::get_bundle_regular_price()` **sums the regular price of each purchasable course product** |
| Product | `_tutor_course_product_id` on the bundle; created only when `monetize_by = wc` and a price > 0 exists |
| Enrollment marker | `_tutor_bundle_id` on each `tutor_enrolled` post |
| Status | Bundles can be saved as `draft` from the builder; keep them draft until price and copy are approved |

Two things that bite:

1. **There is no automatic discount.** The bundle price is the sum of its parts unless you set the bundle's own price/sale price in the builder. If the offer is "3 courses for the price of 2", that is a manual price, and it must be re-checked whenever a member course changes price.
2. **Bundle enrollment only fires on `completed`.** `TutorPro\CourseBundle\Integrations\WooCommerce::bundle_course_enrol()` returns early unless `'completed' === $status_to`. Course enrollment (single product) has the auto-complete escape hatch; bundles do not. On a store with auto-complete off, a bundle order sitting in `processing` enrolls nobody.

Verify:

```bash
wp eval 'var_export(get_post_meta(<bundle_id>, "bundle-course-ids", true));'
wp eval 'echo \TutorPro\CourseBundle\Models\BundleModel::get_bundle_regular_price(<bundle_id>);'
wp eval 'var_export(tutor_utils()->is_enrolled(<course_id>, <user_id>));'   # for each course in the bundle
```

A free bundle (`reg_price = 0`) gets no product and no purchase path. Decide whether that is intended.

## 7. Coupons

WooCommerce coupons (`WC_Coupon`) are the only coupons that exist in this stack. Tutor's native coupon screen belongs to Tutor's own ecommerce engine; it does nothing to a WooCommerce cart.

Checklist before publishing a code:

- Discount type (`percent`, `fixed_cart`, `fixed_product`), amount, and expiry.
- Restrictions: minimum/maximum spend is calculated on the cart subtotal; product/category restrictions must cover every course in a bundle or the coupon quietly does nothing.
- `Individual use only` blocks stacking. If stacking is allowed, test it — and test it against a **sale price** and against tax, because that is where totals stop matching the invoice.
- Usage limits: per coupon and per user. Coupons apply to renewals differently (WooCommerce Subscriptions has its own coupon handling); test a renewal before promising a lifetime discount.

Verify with the order, not the coupon screen:

```bash
wp eval '$o=wc_get_order(<order_id>); foreach($o->get_coupon_codes() as $c) echo $c."\n"; echo $o->get_total_discount();'
```

## 8. Subscriptions and memberships

### WooCommerce Subscriptions (`wc-subscriptions` addon)

Requires **WooCommerce Subscriptions** (separate paid plugin) + WooCommerce + Tutor + Tutor Pro. The addon hooks `woocommerce_subscription_status_updated` to move the enrollment out of `completed` when the subscription stops being `active`, and filters `tutor_is_enrolled` so a course whose product is of type `subscription` / `variable-subscription` requires a subscription that actually contains that product.

Prerequisites that are easy to miss:

- The product must be a **subscription product**, not a simple product with recurring copy.
- The gateway must support automatic recurring charges, or you must enable **Accept Manual Renewals** in WooCommerce → Settings → Subscriptions, and then someone has to chase every renewal by hand.
- Renewal orders do not re-enroll (the addon suppresses `tutor_after_enrolled` on renewal orders), so the "Course Enrolled" email will not repeat every month. That is correct, but it means renewal receipts are a WooCommerce concern.

### Memberships (PMPRO / Restrict Content Pro)

Legitimate when access is **site-wide or category-wide** rather than per course, and the client already runs the membership plugin. Tutor ships `pmpro` and `restrict-content-pro` addons for those stacks.

### When not to use either

Say no, with reasons, when:

- the offer is a one-time course with no recurring value — a subscription adds churn, dunning and refund questions for zero benefit;
- the gateway cannot do automatic renewals and nobody will process them manually;
- the client wants "lifetime access with a payment plan" — that is a coupon or a manual invoice, not a subscription;
- two engines would run at once (membership plugin + per-course Woo product selling the same course). Pick one access model per course, or you will debug "why does this student have access twice" forever.

## 9. Abandoned cart recovery

**Version ceiling — this matters.** WooCommerce core shipped an experimental abandoned-cart recovery email in **11.0**, and **removed it in 11.2**. It exists only in 11.0 and 11.1. On 11.2+ you must use a dedicated plugin or an ESP.

What 11.0/11.1 do, verified in source:

| Item | Value |
|---|---|
| Feature flag | WooCommerce → Settings → Advanced → Features → Abandoned cart recovery (off by default) |
| Email | `customer_abandoned_cart_recovery`, settings option `woocommerce_customer_abandoned_cart_recovery_settings` |
| Automated send | `automated = yes`, scheduled **2 hours** after a `pending` order is created |
| Automated eligibility | `pending` only (classic checkout) |
| Manual send | order actions → "Send abandoned cart recovery email"; eligible statuses `pending` and `checkout-draft`, at least **1 hour** old, valid billing email |
| Action Scheduler hook | `woocommerce_send_abandoned_cart_recovery_notification` |
| Dedup meta | `_abandoned_cart_recovery_email_sent_at` (one email per order) |
| Filter | `woocommerce_abandoned_cart_recovery_eligible_statuses` |
| Suppression | does nothing if AutomateWoo or MailPoet is active; honours unsubscribe |

What it cannot do, on any version: email someone who left **before** entering an email address. A cart with no order and no billing email has no recipient. Cart-stage recovery needs a plugin that captures the email earlier, or an ESP that tracks identified shoppers. Also note the automated path ignores `checkout-draft`, so a block-checkout abandonment is only recoverable manually.

Test it without waiting two hours: place an order that fails payment, then use the manual order action, and confirm the email lands in Mailpit/FluentSMTP logs. Run the queue if needed:

```bash
wp action-scheduler run
```

## 10. Emails

Two systems send mail, and they fail for different reasons.

### WooCommerce emails (orders and account)

`WC_Email_*`: new order (admin), processing, completed, on-hold, cancelled, failed, refunded, partially refunded, customer invoice, new account, reset password. Each has a settings option `woocommerce_<email_id>_settings` with `enabled`, and each is filterable with `woocommerce_email_enabled_<email_id>`.

From identity is global: `woocommerce_email_from_name` and `woocommerce_email_from_address`.

### Tutor emails (learning)

The `tutor-email` addon owns these, stored in `tutor_option['email_to_students']` / `email_to_teachers` / `email_to_admin`, with the From identity in `tutor_email_default_config` (`email_from_name`, `email_from_address`).

Verified defaults that surprise people:

| Trigger | Key | Default |
|---|---|---|
| Welcome after registration | `welcome_student` | **on** |
| Course enrolled | `course_enrolled` | **on** |
| Completed a course | `completed_course` | **off** |
| Reminder to inactive students | `inactive_student` | **off** (`inactive_days` = 10) |
| Assignment graded | `assignment_graded` | off |
| Quiz completed | `quiz_completed` | off |
| New announcement | `new_announcement_posted` | off |

Read them with dot notation:

```bash
wp eval 'foreach(["welcome_student","course_enrolled","completed_course","inactive_student","quiz_completed","assignment_graded"] as $k) { echo $k."=".(tutor_utils()->get_option("email_to_students.".$k)?"on":"off")."\n"; }'
wp eval 'var_export(get_option("tutor_email_default_config")["email_from_address"] ?? null);'
```

Tutor's "Course Enrolled" mail fires from `tutor_after_enrolled` — which, per §1, only fires on a transition to `completed`. If the student paid and got no email, check the order status and the enrollment status before touching the email template.

Tutor's bulk/cron emails (new lesson, new quiz, new assignment) require **WP Cron for Bulk Mailing** to be on; the transactional ones are not affected.

### SMTP plugin (FluentSMTP example)

Role: take everything `wp_mail()` produces, deliver it through one authenticated provider, and log it.

| Setting | Why it matters here |
|---|---|
| From Email + Force From Email | Overrides whatever From Tutor or WooCommerce set. This is the fix for "From: wordpress@host" and for the From/DMARC mismatch below. |
| Force Sender Name | Same, for the display name. |
| Set return-path to match From | Bounces come back where you can read them. |
| Log Emails | On by default. Every send and every failure is recorded. |
| Email Simulation | Writes to the log without delivering. Use on staging so QA never mails a real customer. |
| Delete Logs | Retention window (7 days – 2 years). Raise it before debugging an older incident. |

Log table: `{prefix}fsmpt_email_logs`, columns `to`, `from`, `subject`, `status`, `response`, `created_at`.

```sql
SELECT created_at, status, `to`, subject, LEFT(response, 120) AS response
FROM {prefix}fsmpt_email_logs
ORDER BY created_at DESC LIMIT 20;
```

A `failed` row with the provider's own text ("Email address is not verified", 401, "SPF validation failed") is the fastest diagnosis you will get. Resend only after fixing the cause.

Do not run two SMTP plugins. Two interceptors means one of them loses silently.

### SPF, DKIM, DMARC — the part that decides inbox vs spam

- **SPF** authorises the *envelope* sender's servers. One TXT record per domain, and it must include the provider that FluentSMTP is actually using. Two SPF records = broken SPF.
- **DKIM** signs the message. The provider gives you a TXT record at a selector subdomain; it must be published and the signature must be active.
- **DMARC** requires **alignment**: SPF or DKIM must pass *for the domain in the visible From*. `p=none` monitors; `p=quarantine`/`p=reject` enforce. Alignment is what people get wrong.

The concrete failure mode: the client's official mailbox is a Gmail address, so someone sets `From: cliente@gmail.com` while the site sends through the host or an ESP. SPF for gmail.com does not authorise that server, DKIM is not signed for gmail.com, so DMARC fails at the receiver — even though "SPF passed" may appear in the headers, because it passed for the *envelope* domain, not the From domain.

Correct pattern:

- **From**: `no-reply@<site-domain>` (or `cursos@`), on the domain whose SPF/DKIM you published.
- **Reply-To**: the client's real inbox.
- **From Name**: the brand.
- Publish the provider's records, wait for propagation, then send to a Gmail and an Outlook address and read `Authentication-Results` in the headers — not just "it arrived".

### Templates without forking a plugin

- **Tutor**: edit subject, heading, body, logo and colours in Tutor LMS Pro → Settings → Email. Changes live in `tutor_option` / `tutor_email_default_config`, so they survive plugin updates. Use placeholders; never paste a static course name into a shared template.
- **WooCommerce**: edit subject/heading/body in WooCommerce → Settings → Emails. Block-based email editing is a feature flag (`block_email_editor`), not the default on every 11.x install — confirm which editor the site is on before promising a design. For layout changes, use the `woocommerce_email_*` hooks (`woocommerce_email_order_details`, `woocommerce_email_footer`) from a small plugin.
- **Never** copy WooCommerce or Tutor email templates into the theme. A copied `emails/` folder freezes the markup and silently misses upstream fixes and security patches.
- **Brand consistency**: one logo, one primary colour, one footer. Keep the legal sender identity and address in the footer of every commercial mail.

### Sequences that actually move the business

Build these from Tutor triggers plus WooCommerce order emails; do not hand-send them.

1. **Welcome** — `welcome_student` (on). Sets the dashboard URL and one first action.
2. **Purchase confirmation** — WooCommerce processing/completed (receipt, order number) **and** Tutor `course_enrolled` (how to start). They serve different jobs; keep both.
3. **Reminder** — `inactive_student` with a sensible `inactive_days` (7–14). Needs the bulk-cron option on.
4. **Completion** — `completed_course` (off by default; turn it on) plus `send_certificate_link_to_course_completion_email` so the diploma link is in the mail.
5. **Certificate** — delivered by the completion email or the dashboard certificates page. Verify a real completed student, not a preview.

Keep every placeholder that the template declares; an unresolved `{course_name}` looks worse than no email. Placeholder list per trigger is in Tutor's docs (see Sources).

## 11. Test a real purchase, end to end

Do this on staging or DDEV, with a real gateway in test mode. Do not mock payment away entirely.

1. **Clean state.** New browser profile or incognito, empty cart, logged out.
2. **Product sanity.** Product is Simple, Virtual, priced, tax class set; course `_tutor_course_price_type = paid` and the product linked. `is_course_purchasable()` is true.
3. **Add to cart.** Course page shows the buy button and the price matches the product. Cart shows the same number.
4. **Coupon.** Apply it (if the offer has one). Total drops by exactly the expected amount; tax recalculates.
5. **Checkout.** Fill billing. If guest mode is off, confirm the login modal and that login returns to checkout, not to `wp-admin/profile.php`.
6. **Pay.** Stripe test cards (from Stripe's testing docs): `4242 4242 4242 4242` success, `4000 0025 0000 3155` 3DS required, `4000 0000 0000 3220` 3DS2 required, `4000 0000 0000 9995` declined.
7. **Order.** Status is what you designed for (`completed` if virtual+downloadable or auto-complete on; otherwise `processing`). Transaction ID present. No duplicate orders.
8. **Enrollment.** A `tutor_enrolled` post exists with `post_author` = the buyer, `post_parent` = the course, `post_status = completed`, and `_tutor_enrolled_by_order_id` = the order.
9. **Access.** The student can open a lesson (HTTP 200, real content), progress counts, dashboard lists the course.
10. **Emails.** In Mailpit (DDEV) or the FluentSMTP log: WooCommerce receipt, Tutor "Course Enrolled", and the welcome mail if the account was created in the flow. Open each and check From, Reply-To, and that the CTA links resolve.
11. **Refund.** Full refund from WooCommerce → order becomes `refunded`, enrollment stops being `completed`, access is gone. Then re-run the purchase to leave the site in a sellable state.
12. **Mobile.** Repeat steps 3–7 at 390×844. Payment sheets and the address autofill are where checkout actually breaks.

Record the evidence — order ID, enrollment ID, log entries — in the handover. "It worked" is not evidence.

## 12. Clean up test data

Test orders, enrollments and users pollute revenue reports and reconciliation. Remove them — on **local or staging only**. Production orders are financial records: cancel or refund, never delete.

```php
// 1. Find everything from the QA address (HPOS-safe, no wp_posts queries).
$orders = wc_get_orders( [ 'billing_email' => 'qa.student@example.com', 'limit' => -1 ] );
foreach ( $orders as $order ) {
    printf( "%d  %s  %s\n", $order->get_id(), $order->get_status(), $order->get_total() );
}
```

```php
// 2. Delete the Tutor enrollment records that point at those orders, then the orders.
foreach ( $orders as $order ) {
    $enrollments = get_posts( [
        'post_type'   => 'tutor_enrolled',
        'post_status' => 'any',
        'numberposts' => -1,
        'meta_key'    => '_tutor_enrolled_by_order_id',
        'meta_value'  => $order->get_id(),
    ] );
    foreach ( $enrollments as $e ) {
        wp_delete_post( $e->ID, true );
    }
    $order->delete( true );   // force delete: no trash row, no report line
}
```

```bash
# 3. Lesson/course completion leftovers and the QA user.
wp eval 'foreach (get_user_meta(<user_id>) as $k => $v) { if (strpos($k, "_tutor_completed_lesson_id_") === 0) delete_user_meta(<user_id>, $k); }'
wp eval 'global $wpdb; $wpdb->delete($wpdb->comments, ["comment_agent" => "TutorLMSPlugin", "comment_type" => "course_completed", "user_id" => <user_id>]);'
wp user delete <user_id> --reassign=1
```

```bash
# 4. Queues, caches and mail logs.
wp action-scheduler run
wp cache flush
wp eval 'global $wpdb; $wpdb->delete($wpdb->prefix."fsmpt_email_logs", ["to" => "qa.student@example.com"]);'
```

Do not leave the test product published, and do not leave a coupon with `usage_limit = 1` consumed by QA. Both are invisible until a customer hits them.

**Bundle test orders need an extra pass.** Bundle member enrollments are created with order ID `0` (they carry `_tutor_bundle_id` instead), so the query above misses them:

```php
foreach ( get_posts( [ 'post_type' => 'tutor_enrolled', 'post_status' => 'any', 'numberposts' => -1,
                       'meta_key' => '_tutor_bundle_id', 'meta_value' => <bundle_id> ] ) as $e ) {
    if ( (int) get_post_field( 'post_author', $e->ID ) === <user_id> ) {
        wp_delete_post( $e->ID, true );
    }
}
```

## 13. Symptom → cause → verification

| Symptom | Most likely cause | Verification |
|---|---|---|
| Course page shows no price and no buy button | `monetize_by` is not `wc`; or `_tutor_course_price_type` is not `paid`; or `_tutor_course_product_id` is 0 | `tutor_utils()->get_option("monetize_by")`; `get_post_meta($id,"_tutor_course_price_type",true)`; `is_course_purchasable($id)` |
| Price on the course page ≠ price in the cart | Price edited in the wrong place (course meta vs product), or a stale sale schedule | Compare `wc_get_product($pid)->get_price_html()` with the order subtotal; check `_sale_price_dates_to` |
| Currency symbol wrong | Tutor currency settings were changed; under WC they are ignored | `get_option("woocommerce_currency")` vs what the page prints |
| Add to cart does nothing | Product not linked, or `is_purchasable()`/`is_in_stock()` false | `wc_get_product($pid)->is_purchasable()`; product stock status; `product_belongs_with_course($pid)` |
| Order paid, student has no access | Enrollment is `processing`/`pending`, not `completed` (virtual-but-not-downloadable + auto-complete off) | `wp post list --post_type=tutor_enrolled --post_parent=<course_id>`; check `post_status`; `is_enrolled($course_id,$uid)` |
| Order paid, bundle courses not enrolled | Bundle enrollment requires `completed`, and nothing else fires it | Order status is `processing`? Check `bundle-course-ids` and `_tutor_bundle_id` |
| Order exists but no enrollment at all | Product not linked to a course (Tutor never saw it), or order created outside the checkout flow | `product_belongs_with_course($product_id)`; `$order->get_meta("_is_tutor_order_for_course")` |
| Duplicate enrollments / duplicate earnings | Re-run of checkout, retried payment, or a manual enrollment on top | Count `tutor_enrolled` per course+user; `tutor_earnings` rows per `order_id` |
| "Course Enrolled" email never arrives | `tutor_after_enrolled` did not fire (status never reached `completed`), or the trigger is off | Order status; `email_to_students.course_enrolled`; FluentSMTP log for the recipient |
| No order email at all | WooCommerce email disabled, or `wp_mail` failing, or no SMTP plugin | `woocommerce_<id>_settings.enabled`; FluentSMTP log; `wp eval 'wp_mail("you@example.com","t","b");'` |
| Mail arrives, lands in spam / DMARC fail | From domain not aligned with SPF/DKIM (e.g. a Gmail From), or two SMTP plugins | Read `Authentication-Results` in the received message; confirm Force From Email is the authenticated domain |
| "Send abandoned cart recovery email" action missing | WooCommerce 11.2+ (feature removed), feature flag off, order not `pending`/`checkout-draft`, under 1 hour old, no billing email, or already sent | `WC_Email_Customer_Abandoned_Cart_Recovery::EMAIL_ID` exists? Check version; check `_abandoned_cart_recovery_email_sent_at` |
| Refund issued at Stripe, student still has access | Refund done in the Stripe dashboard, so the WooCommerce order never changed | WooCommerce order status is still `completed`? Refund must be created in WooCommerce |
| Guest purchase, student cannot log in to the course | Guest checkout created a customer without a usable account, or the enrollment is tied to a guest id | `$order->get_customer_id()`; `_tutor_wc_guest_customer_id` on the course; `woocommerce_guest_session_to_user_id` fired |
| Everything looks fine in admin, broken on mobile | Payment sheet/autofill/3DS path not tested on a device; caching on checkout | Repeat steps 3–7 at 390×844 with devtools throttling; confirm checkout is excluded from cache |

## 14. Sources

**Tutor LMS / Tutor Pro**

- WooCommerce integration, product creation and linking — https://tutorlms.com/docs/woocommerce
- Monetization settings (single engine at a time) — https://tutorlms.com/docs/settings/monetization-settings/
- Course bundle — https://tutorlms.com/docs/course-bundle
- WooCommerce Subscriptions — https://tutorlms.com/docs/third-party-integrations/woocommerce-subscriptions
- Email addon and triggers — https://tutorlms.com/docs/addons/email-addon/ · https://tutorlms.com/docs/tutor-lms-settings-email
- Email placeholders — https://tutorlms.com/docs/tutorials/email-placeholders-list
- Plugin source read on 2026-11-13: `tutor/classes/WooCommerce.php`, `tutor/classes/Course.php`, `tutor/models/EnrollmentModel.php`, `tutor/classes/Utils.php`, `tutor-pro/addons/course-bundle/src/Integrations/WooCommerce.php`, `tutor-pro/addons/course-bundle/src/Models/BundleModel.php`, `tutor-pro/addons/wc-subscriptions/classes/WCSubscriptions.php`, `tutor-pro/addons/tutor-email/classes/EmailData.php`, `EmailSettings.php`, `EmailNotification.php`

**WooCommerce**

- Order statuses and order management — https://woocommerce.com/document/managing-orders/
- Checkout block / Store API — https://woocommerce.com/document/woocommerce-blocks/
- HPOS (custom order tables) — https://woocommerce.com/document/high-performance-order-storage/
- Abandoned cart recovery emails (11.0/11.1 only; removed in 11.2) — https://woocommerce.com/document/managing-orders/abandoned-cart-recovery-emails/
- WooCommerce 11.0 release notes (feature introduced as experimental) — https://developer.woocommerce.com/2026/08/04/woocommerce-11-0/
- Source read on 2026-11-13, WooCommerce 11.1.2: `includes/class-wc-order.php` (`needs_processing()`, `payment_complete()`), `includes/wc-order-functions.php` (`wc_order_fully_refunded`), `includes/emails/class-wc-email-customer-abandoned-cart-recovery.php`, `src/Internal/AbandonedCartRecovery/Scheduler.php`, `includes/abstracts/abstract-wc-settings-api.php`

**Stripe**

- Testing: test cards, 3DS cards, declines — https://docs.stripe.com/testing
- WooCommerce Stripe Gateway 11.0.0 source read on 2026-11-13: `includes/class-wc-stripe-webhook-handler.php` (signature validation, `woocommerce_api_wc_stripe`), `includes/class-wc-stripe-logger.php`, `includes/class-wc-stripe-helper.php` (`SETTINGS_OPTION`)

**SMTP plugin (FluentSMTP example)**

- Settings, Force From Email, log retention, simulation — https://fluentsmtp.com/docs/fluent-smtp-settings
- Email logs — https://fluentsmtp.com/docs/fluentsmtp-email-logs-feature
- Other SMTP / host mailboxes (ports, SPF/DKIM advice) — https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer
- Plugin source read on 2026-11-13, FluentSMTP 2.4.5: `boot.php` (`fsmpt_` prefix), `database/migrations/EmailLogs.php`, `app/Services/Mailer/BaseHandler.php` (`force_from_email`)

**Payments, generally**

- WooCommerce PayPal Payments — https://woocommerce.com/document/woocommerce-paypal-payments/
- WooCommerce Subscriptions — https://woocommerce.com/document/woocommerce-subscriptions/
