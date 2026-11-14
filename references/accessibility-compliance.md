# Accessibility and legal compliance for Tutor LMS courses

> This file is an operating reference, not legal advice. Every item marked **[ABOGADO]** needs a licensed attorney (and, where noted, a privacy professional or accountant) to review the actual facts: entity, states served, contracts, and data flows. Do not paste this document into a client deliverable as a compliance certificate.

Scope: a Tutor LMS 3.x/4.x course with video lessons, quizzes, assignments, certificates and optional WooCommerce checkout. The accessibility part applies to any Tutor install. The legal part is a reusable checklist: use the section that matches your jurisdiction and sector. A US immigration **training** academy is included as a worked example, not as an assumption about your client.

---

## 0. Two rules that prevent most bad audits

1. **A green automated score is not conformance.** axe/Lighthouse evaluate a fixed rule set. They cannot judge caption accuracy, focus order, reading order, whether an error message is understandable, or whether a timer is usable. WCAG 2.2 conformance is evaluated on **full pages and complete processes**, not on one scanned URL [1][7].
2. **Do not claim a standard you did not test.** Write "auditado contra WCAG 2.2 AA en [scope], [date]; brechas conocidas: …". Never "sitio 100% accesible" or "certificado WCAG". A vendor cannot certify WCAG conformance for someone else's content.

---

## 1. WCAG 2.2 AA applied to a course

The table lists what actually breaks in Tutor courses, with the success criterion (SC) and level. Levels matter: captions are **A**, not AA. The six new A/AA criteria in 2.2 are covered where they apply [2].

### 1.1 Video and audio

| Requirement | SC | Level | What to check in Tutor |
|---|---|---|---|
| Captions for prerecorded audio | 1.2.2 | **A** | Every lesson video, preview, promo and certificate explainer. Captions must include speaker changes and non-speech sound. Auto-generated captions are a draft, not a deliverable. |
| Audio description or media alternative | 1.2.3 | A | Screen-recorded form tutorials ("click here", "this field") carry meaning in the image. Either audio-describe them or provide a descriptive transcript that names the field and the action. |
| Audio description | 1.2.5 | **AA** | 1.2.3 allows AD *or* a media alternative; 1.2.5 requires AD. A transcript satisfies 1.2.3, not 1.2.5. If there is no AD, record it as a known gap. |
| Live captions | 1.2.4 | AA | Only if live classes are part of the offer (Zoom/Meet). Auto-captions in the meeting platform are a mitigation, not a guarantee. |
| Audio-only content | 1.2.1 | A | Podcasts or voice notes: transcript. |
| Audio that autoplays | 1.4.2 | A | A lesson that starts audio automatically for >3 s needs pause/stop or independent volume. |
| No seizure-inducing flashes | 2.3.1 | A | Check animated intros and GIFs. |
| Motion can be reduced | 2.3.3 | AAA | Best practice: honor `prefers-reduced-motion`; do not make it a launch blocker unless the client targets AAA. |

Practical rules for this niche:

- **Do not rehost USCIS/EOIR PDFs or videos.** Link to the official `.gov` source. Rehosting creates stale forms, accessibility debt, and a consumer-protection problem (the government publishes forms free) [24].
- For a form walkthrough, the accessible artifact is the **descriptive transcript**: timestamp, field name, what to enter, why, and what error to avoid. It doubles as the student's study sheet.
- Captions in Spanish need correct orthography and accents: `año` ≠ `ano`, `petición` ≠ `peticion`. Review form names (I-589, I-914, I-765) because caption engines mangle them.
- If the video is on YouTube/Vimeo unlisted, the captions live on the platform. Export the `.vtt`/`.srt` and archive it as evidence; a platform toggle is not proof of quality.

### 1.2 Text, color and zoom

| Requirement | SC | Check |
|---|---|---|
| Contrast 4.5:1 normal text, 3:1 large (≥24 px, or ≥18.66 px bold) | 1.4.3 | Tutor buttons, meta labels, lesson sidebars, quiz feedback. Measure with a real picker against the rendered background, not the design token. |
| Non-text contrast 3:1 | 1.4.11 | Input borders, radio/checkbox outlines, focus rings, icons that carry meaning. |
| Not color alone | 1.4.1 | Correct/incorrect answers, required fields, progress, "pending" status. Add text or icon. |
| Resize text to 200% | 1.4.4 | No clipped labels or overlapping controls. |
| Reflow at 320 CSS px | 1.4.10 | No horizontal page scroll in lesson, quiz or checkout. |
| Text spacing | 1.4.12 | Line height 1.5, paragraph 2×, letter 0.12×, word 0.16× without loss. |
| Content on hover/focus | 1.4.13 | Tooltips in forms stay open, dismissible, hoverable. |
| Images of text | 1.4.5 | No text baked into thumbnails, certificates or diagrams when real text is possible. |

Spanish copy runs longer than English: test the actual translated strings, not the English mock. A button that fits "Next" may break with "Continuar a la siguiente lección".

### 1.3 Keyboard and focus

- **2.1.1 Keyboard (A):** every function works with keyboard only: curriculum accordion, lesson tabs, video controls, quiz radios, file upload, modal, checkout, certificate download.
- **2.1.2 No Keyboard Trap (A):** you can always Tab away. Video players and embedded iframes are the usual traps.
- **2.4.3 Focus Order (A):** focus follows visual order; opening an accordion does not send focus to the footer.
- **2.4.7 Focus Visible (AA):** a visible indicator on every control. `outline: none` without a replacement is a fail.
- **2.4.11 Focus Not Obscured (Minimum) (AA, new in 2.2):** a sticky header, cookie banner or chat widget must not fully cover the focused element. Test at 400% zoom, where the header eats the viewport.
- **2.5.7 Dragging Movements (AA, new):** if a quiz question or file upload requires drag-and-drop, provide a click/keyboard alternative.
- **2.5.8 Target Size (Minimum) (AA, new):** 24×24 CSS px, with spacing exceptions. Mobile lesson nav, quiz radios, close buttons and tabs are the usual offenders. 44×44 remains the comfortable target.
- **2.1.4 Character Key Shortcuts (A):** single-letter shortcuts must be remappable or only active on focus.

### 1.4 Page structure and navigation

- **2.4.1 Bypass Blocks (A):** skip link to main content. Tutor dashboards are nav-heavy.
- **2.4.2 Page Titles (A):** the lesson title is in `<title>`, and it changes after AJAX navigation. If the quiz loads without a new title, fix it.
- **1.3.1 Info and Relationships (A):** real headings (`h1`→`h2`→`h3`), lists, `fieldset`/`legend`, table headers, not styled `div`s.
- **2.4.6 Headings and Labels (AA):** descriptive, not "Haz clic aquí".
- **3.1.1 Language of Page (A) / 3.1.2 Language of Parts (AA):** the page declares `lang="es"` (or `es-US`), and English terms inside Spanish copy carry `lang="en"`. This is a common failure on a Spanish course built on an English WordPress install; a screen reader otherwise pronounces "deadline" and "waiver" as Spanish.
- **3.2.3 / 3.2.4 Consistent Navigation and Identification (AA):** the same control means the same thing on course, lesson and quiz.

### 1.5 Quiz

| Issue | SC | Test |
|---|---|---|
| Labels and instructions | 3.3.2 (A), 1.3.1 (A) | Each question is a `fieldset` with a `legend`; the radio group is announced with its question. |
| Input purpose | 1.3.5 (AA) | Name/email fields in the quiz intro or assignment use `autocomplete`. |
| Errors are identified in text | 3.3.1 (A) | "Falta responder la pregunta 4", not only a red border. |
| Error suggestion | 3.3.3 (AA) | Say what to do: "Selecciona una respuesta antes de continuar." |
| Error prevention on submission | 3.3.4 (AA) | SC 3.3.4 explicitly covers **submitting user test responses**: provide review/confirm, or a reversible edit before final submit. |
| Status messages | 4.1.3 (AA) | "Guardando…", "Respuesta enviada", "Tiempo restante" announced without moving focus. A ticking clock should not be a live region every second; announce milestones. |
| Timing | 2.2.1 (A) | A quiz timer must be adjustable/extendable or switchable off, unless a strict exception applies. "Essential" is arguable for assessment, but accommodations are still expected; prefer an untimed attempt or an extension mechanism. Warn at least 20 seconds before expiry. |
| Auto-advance on selection | 3.2.2 (A) | Selecting a radio must not silently submit or move to the next question. |
| Drag-and-drop question types | 2.5.7 (AA) | Provide a non-drag alternative. |
| Result page | 1.4.1 (A), 1.3.1 (A) | Pass/fail in text, score in text, answer review keyboard-accessible, next step clear. |
| Images in questions | 1.1.1 (A) | Alt text describes the form/field; decorative images use `alt=""`. |
| Audio in questions | 1.4.2 (A) | No autoplay audio without control. |

Tutor stores the timer and attempts in the serialized `tutor_quiz_option` (from `references/data-model.md`): `time_limit`, `attempts_allowed`, `passing_grade`. Inspect them before testing:

```bash
wp eval '
$q = new WP_Query(["post_type"=>"tutor_quiz","posts_per_page"=>-1,"post_status"=>"publish"]);
foreach ($q->posts as $p) {
  $o = maybe_unserialize(get_post_meta($p->ID, "tutor_quiz_option", true));
  printf("%d\t%s\ttime_limit=%s\tattempts=%s\tpass=%s\n",
    $p->ID, $p->post_title, $o["time_limit"] ?? "-", $o["attempts_allowed"] ?? "-", $o["passing_grade"] ?? "-");
}'
```

### 1.6 Assignment

- File input has a real `<label>`, states accepted types and max size **before** upload.
- Errors are specific: "El archivo supera 10 MB", not "Error".
- Upload progress and "Enviado / Pendiente de calificación" are announced (4.1.3).
- Drag-and-drop upload has a click-to-browse alternative (2.5.7).
- Deadline and time zone are written in text; a countdown alone is not enough (`time_duration` in `assignment_option`).
- Do not ask again for data the student already provided (3.3.7 Redundant Entry, A, new in 2.2).
- Review/confirm before final submission (3.3.4, since it is a user submission that may affect grading).

### 1.7 Messages and dynamic content

Tutor's AJAX flows (complete lesson, save quiz, upload assignment, apply coupon) must announce state:

- `role="status"` or `aria-live="polite"` for success and progress.
- `role="alert"` or `aria-live="assertive"` for errors that need immediate attention.
- Never announce a whole page region repeatedly; scope the live region to the message.
- **4.1.3 Status Messages (AA)** is one of the most-missed criteria in LMS interfaces. "Progreso guardado" that appears visually but is not announced leaves a screen reader user unsure whether the action worked.

### 1.8 Tables

- **1.3.1 (A):** data tables use `<th scope="col|row">`; complex tables use `headers`/`id`.
- Caption or `aria-label` states the purpose ("Calendario de pagos del curso").
- No layout tables for pricing or curriculum; use CSS.
- Sortable columns, if any, must be operable by keyboard and announce the sort state.

### 1.9 PDFs and downloadable resources

WCAG applies to the PDF, not just the HTML page that links it.

- Tagged PDF with a logical reading order, document language, title, alt text, and form-field labels.
- Scanned PDFs need OCR **and** a reading-order fix; OCR text alone is not an accessible document.
- **1.4.5 (AA):** no images of text; **1.4.9 (AAA)** is stricter but a good target for worksheets.
- Every PDF needs an HTML alternative when the PDF is the only way to complete a task.
- Quick triage:

```bash
pdftotext -layout file.pdf - | head -40   # empty → likely a scan
pdfinfo file.pdf | grep -E 'Pages|Tagged|Encrypted'
```

- Structural check with PAC 2024 (free) or Adobe Acrobat Pro's Accessibility Check; validate PDF/UA (ISO 14289-1) with veraPDF when the client claims PDF/UA [8].
- Certificates issued as PDFs follow the same rules: real text for name/course/date, alt text for the seal, tagged structure.

### 1.10 Images and alt text

- **1.1.1 (A):** informative image → concise alt that conveys the purpose, not the pixels. Decorative → `alt=""`. Functional (icon inside a link/button) → accessible name on the control.
- Complex diagrams (a process flow, a timeline, a form map) → short alt plus a long description or an equivalent list/table.
- Do not put the entire lesson text in alt. Do not write "imagen de…".
- Profile photos in testimonials: alt is the person's name if the name is not adjacent; otherwise decorative.

### 1.11 Login and account

- **3.3.8 Accessible Authentication (Minimum) (AA, new):** no cognitive-function test. Allow paste and password managers. A puzzle CAPTCHA or "transcribe this code" with no alternative is a fail. Prefer passkeys, magic links, or a privacy-friendly challenge with an audio/alternative path.
- **3.3.7 (A):** checkout → account creation → enrollment should not ask for the same data three times.
- **3.2.6 Consistent Help (A, new):** help/contact in the same relative place on course, lesson and quiz.
- **3.3.4 (AA):** paying for a course is a financial transaction; the student must be able to review and correct before confirming.

### 1.12 Mobile and touch

- 390×844: no horizontal page scroll; lesson tabs scroll with a visible cue; next/previous reachable with one hand.
- Target size 24×24 minimum (2.5.8), 44×44 preferred.
- No hover-only information.
- Sticky "Continue" bars must not cover the focused control or the quiz submit button (2.4.11).

---

## 2. Audit a lesson: protocol

### 2.1 Prepare (30 min)

1. Freeze content and record the date, course ID, lesson ID, and the plugin versions (`Tutor LMS`, `Tutor Pro`, theme).
2. Create a QA student and enroll (see `references/qa-student.md`). Audit as **student**, not admin: the admin toolbar and edit links hide real issues.
3. Inventory the lesson's media and documents:

```bash
wp eval '
$q = new WP_Query(["post_type"=>"lesson","posts_per_page"=>-1,"post_status"=>"publish"]);
foreach ($q->posts as $p) {
  $v = maybe_unserialize(get_post_meta($p->ID, "_video", true));
  $a = get_post_meta($p->ID, "_tutor_attachments", true);
  printf("%d\t%s\tsource=%s\tattachments=%s\n",
    $p->ID, $p->post_title,
    is_array($v) ? ($v["source"] ?? "?") : "none",
    is_array($a) ? count($a) : 0);
}'
```

4. List the URLs/states in scope: lesson (logged out preview and enrolled), quiz start, quiz in progress, quiz error, quiz result, assignment form, assignment submitted.

### 2.2 Automated baseline

Run the automated subset with axe-core and Lighthouse, and archive the raw output [9][10]:

```bash
mkdir -p audit/$(date +%F)/lesson-$LESSON_ID
OUT="audit/$(date +%F)/lesson-$LESSON_ID"

npx @axe-core/cli "$URL" \
  --tags wcag2a,wcag2aa,wcag21a,wcag21aa,wcag22aa \
  --save "$OUT/axe.json"

npx lighthouse "$URL" \
  --only-categories=accessibility \
  --output=json --output-path="$OUT/lighthouse.json" \
  --chrome-flags="--headless=new" --quiet
```

Both tools fail on authenticated pages. For a logged-in lesson, run axe inside the browser (axe DevTools extension, Accessibility Insights for Web) or with Playwright:

```js
// audit.mjs — node audit.mjs <url> <storageState.json> <out.json>
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';

const [url, state, out] = process.argv.slice(2);
const browser = await chromium.launch();
const context = await browser.newContext({ storageState: state });
const page = await context.newPage();
await page.goto(url, { waitUntil: 'networkidle' });
const { violations } = await new AxeBuilder({ page })
  .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
  .analyze();
const { writeFileSync } = await import('node:fs');
writeFileSync(out, JSON.stringify(violations, null, 2));
await browser.close();
```

Archive the raw JSON, not a screenshot of the results panel.

### 2.3 Keyboard pass (no mouse)

Record the exact sequence, not "works":

1. Load the page, press Tab once: skip link appears and works.
2. Tab through header → curriculum → lesson content → video → tabs → attachments → next/previous.
3. Confirm each stop has a visible focus ring and is not hidden behind the sticky header (test at 400% zoom).
4. Activate everything with Enter/Space; use arrows inside radio groups and tabs; Esc closes modals/menus.
5. Confirm no trap: after the last control you can Tab back to the browser chrome.
6. Repeat on the quiz and the assignment.

Evidence: a table with `step | element | expected | actual | screenshot`.

### 2.4 Screen reader pass

Minimum: NVDA + Firefox (Windows) or VoiceOver + Safari (macOS). Test the same flow a student uses.

| Check | Expected | Evidence to capture |
|---|---|---|
| Headings list (NVDA `H`, VO rotor) | Logical hierarchy, lesson title present | List of announced headings |
| Landmarks | `banner`, `navigation`, `main`, `contentinfo` | Rotor/landmark list |
| Video controls | Named play/pause, captions, volume, progress | Transcript of announcements |
| Captions track | Available and selected; text matches audio | Screenshot + 2-minute comparison note |
| Attachments | Link name states file type and size | Announcement |
| Progress/completion | Announced after action, not only visual | Live region check |
| Quiz question | Group label + question + options + required state | Transcript |
| Quiz error | Announced, names the question, says what to do | Transcript |
| Result | Score and pass/fail read as text | Transcript |

A useful evidence line: `NVDA 2026.x / Firefox 14x / Windows 11 — "botón, Reproducir video"; el reproductor no expone pista de subtítulos (revisado en el DOM)`.

### 2.5 Visual and cognitive pass

- 200% zoom, 400% zoom, 320 px viewport.
- Text-spacing bookmarklet (1.4.12).
- `prefers-reduced-motion: reduce` and forced-colors / Windows High Contrast.
- Reading level and plain language: instructions before the activity, not after; error recovery explained.
- Spanish quality: the quiz error, the timer warning and the certificate page are the three strings students read under stress.

### 2.6 Severity and release decision

| Severity | Definition | Examples |
|---|---|---|
| Critical | Blocks completion for a group of users | Cannot submit quiz by keyboard; video has no captions at all; checkout unreachable |
| Serious | Major barrier, workaround exists but is not obvious | Sticky header hides focus at 200%; errors not announced; timer cannot be extended on request |
| Moderate | Difficulty with a workaround | Poor alt text; 3.8:1 contrast on normal-size secondary text |
| Minor | Annoyance | Inconsistent heading level; redundant link text |

Do not ship a paid course with Critical or Serious findings open. Say so in the report.

---

## 3. Audit a quiz: protocol

1. **Before starting:** read the instructions. Is the time limit, number of attempts, passing grade and whether answers can be reviewed stated in text? (3.3.2, 2.2.1, 3.3.4)
2. **Keyboard only:** reach the first question with Tab. Arrow through options. Select with Space. Reach "Enviar" without the mouse.
3. **Screen reader:** confirm the group is announced with the question, the options are announced with position and state, and required/validation state is exposed.
4. **Submit incomplete:** trigger the error. Is there an error summary? Does focus move to it? Is the message specific and announced? (3.3.1, 3.3.3, 4.1.3)
5. **Timer:** start it. Is it announced? Can it be paused, extended or disabled? Is there a warning before expiry? (2.2.1)
6. **Result:** score, pass/fail, review of correct/incorrect, retry path. Not color-only (1.4.1). Review is keyboard-operable.
7. **Drag-and-drop:** if a question type requires dragging, test the alternative (2.5.7).
8. **Mobile:** 390×844, submit button reachable, no zoom lock, no horizontal scroll.
9. **Status:** "Guardando respuesta", "Intento enviado" announced (4.1.3).

Quiz-specific evidence to keep:

- `tutor_quiz_option` dump (time limit, attempts, passing grade).
- Screen reader transcript of one full question, one error, one result.
- Keyboard log of the submit path.
- Screenshot of the error state at 200% zoom.
- Note on whether the timer is essential and whether an extension is possible.

---

## 4. Evidence to keep (and how to store it)

A finding is only evidence if a third party can reproduce it without asking you.

```
audit/2026-11-13/
├── scope.md                 # URLs, roles, states, versions, date
├── axe/                     # raw JSON per URL
├── lighthouse/              # raw JSON per URL
├── keyboard/                # per-flow tables + screenshots
├── screen-reader/           # transcripts with AT/browser/OS versions
├── zoom/                    # 200%, 400%, 320px, text-spacing, forced-colors
├── media/                   # caption files, transcript URLs, 2-min comparison notes
├── pdf/                     # PAC/veraPDF reports, pdftotext triage
└── report.md                # findings, severity, owner, retest date
```

Each finding needs: ID, SC + name + level, severity, URL, element/selector, environment (AT + browser + OS + viewport), reproduction steps, expected vs actual, user impact, fix, evidence path, retest result and date.

Store consent records, DPAs and audit reports outside the public web root. If you use a shared drive, restrict it: an accessibility report lists student names and screenshots of the dashboard.

---

## 5. Legal compliance: a reusable checklist

The academy sells **education**. The risk is that marketing, exercises or support drift into **regulated advice** (legal, medical, financial, tax) or into **unsupported outcome claims**. Both are enforceable.

### 5.0 General checklist (start here)

- **Regulated activity.** Does the course teach a regulated profession, or does it advise on a specific person's case, health, taxes or money? If yes, the boundary between education and advice must be written, repeated at checkout, and reviewed by counsel. **[ABOGADO]**
- **Claims.** "Official", "guaranteed", "accredited", "licensed", "certified" and outcome promises need documentary evidence before publication. Keep a claims register.
- **Privacy.** Identify sensitive data (health, legal, financial, immigration status, ethnic origin), the legal basis, retention, processors and cross-border transfers. **[ABOGADO + privacidad]**
- **Consumer.** Refunds, cancellation, auto-renewal, trial terms, and the language of the contract.
- **Accessibility.** ADA Title III / EAA / local law may apply depending on where you sell and who you serve. **[ABOGADO]**
- **Disclaimers.** Say what the course is and what it is not, in the footer, near the price, and in the certificate.
- **Records.** Keep the evidence behind every claim, certificate, consent and review.

**Worked example — US immigration training academy (5.1–5.10).** The rest of this section applies the checklist to that sector. Skip what does not apply; the structure (boundary, claims, privacy, certificates) transfers to other regulated courses.

### 5.1 Unauthorized practice of law (UPL) **[ABOGADO]**

- Only a licensed attorney, or an EOIR-accredited representative working through an EOIR-recognized organization, may give immigration legal advice or represent a person before USCIS/EOIR [15][16][17][18].
- EOIR recognition requires a **nonprofit, tax-exempt** organization; accreditation is tied to that organization and does not follow the individual [16][17]. A for-profit academy **cannot** become an EOIR-recognized organization, and completing a course does **not** make anyone an accredited representative.
- A course on the *fundamentals* of immigration law can be relevant to an accreditation application — EOIR requires documented formal training including at least one course on fundamentals [17] — but the academy may not advertise the course as conferring accreditation, authorization to practice, or "licencia".
- Define the boundary in one paragraph and repeat it where money changes hands:
  - what the course does: explains processes, forms, evidence, ethics and eligibility concepts in general;
  - what it does not do: evaluate a specific case, select a form for a specific person, prepare or file a case, represent anyone, or predict an outcome.
- State law adds its own rules. Examples:
  - **California:** immigration consultants must register with the Secretary of State, file a **$100,000** bond, use a written contract in English **and** the client's language, include a "not an attorney" statement, allow **72-hour** cancellation, and advertising must clearly state the person is not an attorney; non-attorneys may not advertise legal services as "notario" [19].
  - **New York:** immigration service providers must post a "not an attorney" notice, provide a detailed contract in English and the client's language, return original documents, and may not give legal advice or promise special influence [20].
- If the academy only sells courses, the consultant statutes may not apply; if it also "helps with the paperwork", they likely do. That determination is exactly what counsel must make. **[ABOGADO]**

### 5.2 Government affiliation

- Never use USCIS/EOIR/DHS seals, logos, flag imagery in a way that suggests endorsement, or a domain that imitates a government site. The FTC treats fake government websites and government impersonation as deception [24][25].
- Publish, in the footer and near the price: **"No estamos afiliados a USCIS, EOIR ni al gobierno de Estados Unidos. Somos una academia privada e independiente."**
- Do not use "USCIS" in the brand, course name, or ad headline. Refer to the agency only to describe the process.
- Do not claim the course is "aprobado por USCIS" or "avalado por el gobierno": those approvals do not exist for a private course.

### 5.3 Results, testimonials and advertising **[ABOGADO]**

- **FTC Act §5** prohibits deceptive claims; outcome claims need a reasonable basis before publication [21].
- The **Endorsement Guides** (16 CFR Part 255) require truthful, non-misleading testimonials and disclosure of material connections [22].
- The **Consumer Reviews and Testimonials Rule** (16 CFR Part 465, effective 21 Oct 2024) prohibits fake or false reviews, purchased reviews, undisclosed insider reviews, review suppression and fake social-media indicators [23].
- Concretely:
  - "Aprobación garantizada", "100% de éxito", "tu green card en 6 meses" → prohibited unless substantiated, and legal outcomes cannot be guaranteed.
  - A testimonial must be real, typical or clearly labeled as non-typical, and used with written permission.
  - Do not screenshot a student dashboard or a case with real names for ads. Use a fictional, labeled example.
  - Do not imply that a testimonial's result will happen for the viewer.
- Every public claim belongs in a **claims register**: exact wording, evidence file, owner, review date. If a claim has no evidence, delete it.

### 5.4 Certificates and credentials

- Say exactly what the document is: **"Certificado de finalización"**, issued by [academy], for [course], [hours], [date], verification ID.
- Say what it is not: not a professional license, not a government credential, not a degree, not EOIR accreditation, not authorization to practice law.
- "Doble certificación" is acceptable only if two real issuers issue two real documents. Name both issuers and describe the second one precisely. Keep the signed agreement on file.
- Never use "acreditado", "acreditación" or "accredited" unless a recognized accrediting body has actually accredited the institution or program. "Certificado" and "acreditado" are not synonyms.
- Avoid "certificación oficial", "validez oficial", "título universitario" and "créditos" unless they are literally true and documented.
- Verification:
  - Public page: enter certificate ID/hash → show name, course, issue date, status (valid/revoked). Nothing else.
  - Tutor issues a `cert_hash`; do not allow sequential enumeration, add rate limiting, and log lookups.
  - The certificate PDF must be tagged and readable (see 1.9). If it is an image, it is inaccessible and easy to forge.
  - Revocation path: what happens if a certificate was issued in error.
- Keep the issuance log: certificate ID, student, course, completion date, template version, who issued it.

**Authorization to operate as a school [ABOGADO].** Selling certificate programs can trigger state private-postsecondary rules, independent of accreditation. California's BPPE regulates institutions with a physical presence in California and approves many diploma/certificate programs; out-of-state online providers may still have registration and reporting duties. New York's BPSS licenses certain curricula over a threshold. The answer depends on physical presence, program hours, whether the program leads to a state license, and where students reside. Do not publish "validez oficial", "título" or "créditos" before counsel resolves this [33][34].

### 5.5 Student privacy **[ABOGADO + privacidad]**

Which law applies depends on where students are, not where the academy is:

| Regime | Trigger | Core obligations |
|---|---|---|
| **GDPR** (EU/UK students) | Offering courses to, or monitoring, people in the EU/UK | Lawful basis (Art. 6), transparency (Art. 13), rights (Arts. 15–20), records (Art. 30), security (Art. 32), DPIA for high risk (Art. 35), transfer mechanism (Art. 44) [29] |
| **CCPA/CPRA** (California residents) | For-profit doing business in CA and meeting one threshold: gross revenue over **$26,625,000** (2025 adjustment), or buying/selling/sharing PI of **100,000+** consumers/households, or ≥50% of revenue from selling/sharing PI | Notice at collection, rights to know/delete/correct/opt out/limit sensitive PI, contracts with service providers, retention disclosure [27] |
| **State breach laws** | A breach affecting residents | Notification timelines and content vary by state |
| **FERPA** | Only if the institution receives US Department of Education funds (directly or via students) | Consent/records rules; a private academy without such funds is generally outside FERPA, but a university partner may be covered [32] |
| **COPPA** | Collecting data from children under 13 | Verifiable parental consent [31] |

Two facts that change the design:

1. **Legal-adjacent data is sensitive.** CCPA/CPRA lists citizenship or immigration status and racial or ethnic origin as sensitive personal information [27]. Under GDPR, a case narrative can reveal racial/ethnic origin, religion, political opinion, sexual orientation, health or criminal history — Art. 9 special categories, and criminal data is Art. 10 [29]. That requires an Art. 9 condition (often explicit consent), extra security, and likely a DPIA.
2. **Therefore: do not collect real case narratives in exercises.** Use fictional personas ("Caso ficticio: María, nacional de Honduras, entrada sin inspección en 2021") and label them. If a real case story is needed for a lesson, de-identify it, get written permission, and store it with access control.

Minimum data set for a course:

- Account: name, email, password hash, locale.
- Commerce: order ID, amount, tax, payment token (never the card number), refund status.
- Learning: enrollment, progress, quiz attempts/scores, assignment files, certificate record.
- Support: tickets and emails, retained per policy.

Do not collect "just in case": case numbers, court dates, country-of-origin details, criminal history, medical information, or copies of identity/legal documents. If a form field is not needed to deliver the course, remove it.

Data map (one row per data type): `data | source | purpose | lawful basis | retention | recipients/processors | storage location | transfer mechanism`. This is the artifact counsel and the DPO will ask for.

Processors to document with a DPA: hosting (your provider; DDEV is local only), WooCommerce payment gateway, SMTP/email provider, analytics, Meta pixel/CAPI, video host, backup service, LMS plugins that phone home. "We use WordPress plugins" is not a data map.

### 5.6 Cookies, pixels and consent **[ABOGADO + privacidad]**

- **ePrivacy Art. 5(3):** non-essential cookies and similar tracking require prior consent. Analytics and marketing pixels are non-essential [30].
- A consent banner must have **reject** as prominent as **accept**, no pre-ticked boxes, no cookie wall, and an easy way to change the choice later.
- **CCPA/CPRA:** cross-context behavioral advertising counts as "sharing". Provide "Do Not Sell or Share My Personal Information" and honor **Global Privacy Control** [27].
- Meta pixel and Conversions API: do not fire before consent; do not send hashed emails/phone numbers without a lawful basis and a processor agreement; document what events are sent.
- Server-side tracking can bypass the browser banner. Treat it as the same legal question: what data leaves the site, on what basis.
- Keep consent records: timestamp, policy version, categories accepted/rejected, and the text shown.

### 5.7 Email

- **CAN-SPAM** applies to commercial email: accurate From/Reply-To and routing, non-deceptive subject, identification as an ad where required, a valid physical postal address, a working opt-out, opt-outs honored within **10 business days**, and the mechanism functional for at least **30 days** [26].
- Transactional messages (receipt, enrollment, certificate, password reset) are different from marketing. Do not bundle marketing consent into the terms of sale.
- If EU/UK recipients: GDPR/ePrivacy consent for marketing; keep proof of consent.
- Do not buy lists. Do not email a purchased list "to see if it works".
- Every marketing email: unsubscribe link, postal address, and a suppression list that survives imports.

### 5.8 Checkout, payments and subscriptions

- **3.3.4 (AA):** review and correct before paying. Show course, price, taxes, refund policy, and what access includes.
- PCI DSS: use a hosted payment page/iframe (SAQ A/A-EP scope). Never store card data in WordPress.
- Subscriptions/auto-renew: disclose the recurring amount, interval, cancellation method and trial conversion **before** purchase. State auto-renewal laws vary; get counsel review. **[ABOGADO]**
- Refund policy in plain Spanish, linked from checkout and the receipt.

### 5.9 Accessibility law (US and EU) **[ABOGADO]**

- **ADA Title III** (42 U.S.C. § 12181 et seq.; 28 CFR Part 36) prohibits disability discrimination in places of public accommodation. There is **no final DOJ rule setting a specific web standard for private entities**; DOJ enforcement and courts commonly reference WCAG 2.1/2.2 AA. Treat WCAG 2.2 AA as the defensible target, not as a statutory citation [11].
- **ADA Title II** (the 2024 web rule, WCAG 2.1 AA, dates extended in 2026) applies to **state and local governments**, not to a private academy. Do not cite it as the academy's obligation; use it only as evidence of the standard's direction [12].
- **Section 508** applies to federal agencies and recipients of federal funds. If the academy takes federal grants or contracts, it may apply [13].
- **European Accessibility Act** applies to e-commerce and other listed services offered to EU consumers since 28 June 2025; the harmonised standard is EN 301 549, which points to WCAG. Service microenterprises (<10 employees and ≤€2M) are exempt, but member-state law may still apply [35][36]. If the academy markets to Spain or other EU countries, this is in scope.
- **State law:** California's Unruh Civil Rights Act and New York's Human Rights Law are common vectors for web accessibility claims and can apply even where federal requirements are unsettled [14].
- Publish an **accessibility statement**: target standard, scope, known limitations, contact channel, response commitment, last review date. It is not a safe harbor, but it is expected and it helps.

### 5.10 Records and retention

There is no single federal retention period for course records. Define one per data type and justify it; GDPR requires storage limitation (Art. 5(1)(e)) and CCPA requires disclosing the retention period [27][29].

| Data | Suggested starting point | Notes |
|---|---|---|
| Marketing leads | 12–24 months from last interaction | Shorter if no consent; delete on request |
| Enrolled student record (progress, grades) | Duration of access + 2–3 years | Needed for certificates and disputes |
| Certificate issuance log | Long/permanent | Needed for verification and revocation |
| Payment and tax records | Per accountant/tax rules | **[ABOGADO/contador]** |
| Support tickets | 12–24 months | May contain sensitive data; minimize |
| Analytics | 14 months | Configure in the tool |
| Recording of live classes | Short, defined period | See consent below |

Retention must be implemented, not just written: a scheduled deletion job, an export before deletion if needed, and a log of deletions. A policy nobody executes is worse than no policy, because it is a written admission.

**Recording live classes:** several states require all-party consent for recording conversations (California and Florida are common examples). Announce recording, get consent in writing or via a clear in-meeting action, and state how to opt out. **[ABOGADO]**

**Breach response:** name an owner, a 72-hour GDPR notification path, state notification matrix, and a communication template in Spanish and English. Test it once.

---

## 6. What NOT to promise

| Risky claim | Why it is risky | Safer wording |
|---|---|---|
| "Garantizamos la aprobación de tu caso" | Outcome depends on USCIS/EOIR; deceptive under FTC Act §5 | "Te formamos para preparar un expediente mejor documentado. La decisión corresponde a la autoridad." |
| "100% de aprobación" / "tasa de éxito del 98%" | Needs substantiation and a defined denominator | Remove, or publish methodology and evidence |
| "Somos abogados" / "representamos tu caso" | UPL; only attorneys and EOIR-accredited reps may represent [15] | "Somos una academia de formación. No brindamos asesoría legal ni representamos casos." |
| "Te certificamos como representante acreditado" | EOIR accredits individuals through recognized **nonprofit** organizations [16][17] | "El curso cubre los fundamentos que exige la formación de un representante acreditado; la acreditación la otorga EOIR a través de una organización reconocida." |
| "Certificación oficial" / "avalado por el gobierno" | Implies government issuance or endorsement | "Certificado de finalización emitido por [institución]." |
| "Acreditado" / "accredited" | Only true with a recognized accreditor | Name the accreditor and scope, or do not use the word |
| "Somos USCIS" / "estamos afiliados" / .gov-like brand | Government impersonation; FTC enforcement [24][25] | "Academia privada e independiente, no afiliada al gobierno de EE. UU." |
| "Notario" as a service title | Misleading in the US; restricted in several states [24][19] | "Consultor de educación migratoria" / "instructor" |
| "Tramitamos tu visa" / "llenamos tu I-589" | Sounds like legal/form-preparation services | "Enseñamos cómo se prepara el formulario y qué evidencia se documenta." |
| "Trabajo garantizado" / "te conseguimos empleo" | Employment outcome claim | "Formación para desempeñarte en [rol]; no garantiza empleo." |
| "Resultados en 30 días" | False timeline you do not control | Describe the course duration, not the agency's |
| "La única academia" / "la mejor" | Superlative needs proof | "Una de las pocas academias enfocadas en…" (only if true) |
| Fake reviews, bought reviews, undisclosed employees | 16 CFR Part 465 [23] | Real reviews with disclosure and permission |
| "Créditos universitarios" | Only if actually credit-bearing and transcripted | "Certificado de educación continua" (if accurate) |
| "Aprobado por USCIS" | No such approval exists for a private course | Describe the curriculum and the issuer |

---

## 7. Launch checklist

### Accessibility (per course, before publishing)

- [ ] Every video has human-reviewed captions (not raw auto-captions) and a transcript.
- [ ] Form walkthroughs have audio description or a descriptive transcript naming each field.
- [ ] No autoplay with sound; pause/stop available (1.4.2).
- [ ] Text contrast ≥4.5:1, large text ≥3:1, UI borders/focus ≥3:1.
- [ ] Visible focus on every control; nothing hidden by sticky header/banner at 400% zoom (2.4.11).
- [ ] Lesson, quiz and assignment completable keyboard-only, no trap.
- [ ] Quiz timer configurable/extendable, with a warning; `tutor_quiz_option` reviewed.
- [ ] Quiz submission has review/confirm or a reversible edit (3.3.4).
- [ ] Errors are text, specific, announced; focus moves to the error (3.3.1/3.3.3/4.1.3).
- [ ] Status messages ("guardado", "enviado") announced (4.1.3).
- [ ] 200%/400% zoom, 320 px reflow, text spacing, forced colors tested.
- [ ] `lang="es"` on the page; `lang="en"` on English terms.
- [ ] PDFs tagged, reading order checked, OCR verified, or an HTML alternative offered.
- [ ] Alt text reviewed; decorative images `alt=""`.
- [ ] Tables have headers and a caption.
- [ ] Targets ≥24×24 (prefer 44×44) on mobile.
- [ ] Login allows paste/password managers; no cognitive-only CAPTCHA.
- [ ] Accessibility statement published with a contact channel.
- [ ] Evidence archived in `audit/<date>/`.

### Legal and privacy (per launch)

- [ ] UPL boundary paragraph on home, course page, checkout, lesson footer and emails.
- [ ] "Not affiliated with USCIS/EOIR/US government" notice visible.
- [ ] No government seals/logos; no .gov-imitating domain or ad copy.
- [ ] Claims register complete; every credential/result claim has evidence.
- [ ] Certificate wording reviewed; what it is and is not is explicit.
- [ ] Certificate verification page live, rate-limited, tested with a real hash.
- [ ] Second-issuer agreement on file (if "doble certificación").
- [ ] Privacy policy: data categories, purposes, bases, retention, recipients, transfers, rights.
- [ ] Cookie banner: reject as easy as accept; no pixels before consent; GPC honored.
- [ ] DPAs signed with hosting, email, payment, analytics, video and backup providers.
- [ ] DSAR and deletion process tested end-to-end, with a response deadline.
- [ ] Retention schedule implemented (automated deletion or documented manual job).
- [ ] Sensitive data removed from exercises; fictional cases labeled.
- [ ] Marketing email: postal address, unsubscribe, 10-business-day honor, suppression list.
- [ ] Checkout: price, taxes, refund policy, auto-renew disclosure, review step.
- [ ] Live-class recording consent script and log.
- [ ] No enrollment of under-13s without parental consent.
- [ ] Breach runbook with owner and notification path.
- [ ] Attorney sign-off recorded (entity, states, contracts). **[ABOGADO]**

---

## 8. Risk → control → evidence

| Risk | Control | Evidence to keep |
|---|---|---|
| Deaf student cannot follow a lesson | Human-reviewed captions + transcript on every video | `.vtt`/`.srt`, transcript URL, 2-minute review note per video |
| Blind student cannot follow a form walkthrough | Audio description or descriptive transcript naming fields | AD track or transcript with timestamps and field names |
| Keyboard user cannot submit a quiz | Full keyboard pass; fix focus order/trap; test with Tab only | Keyboard log + screenshot of focused submit |
| Screen reader user does not know the quiz saved | Live region for save/submit/upload states | AT transcript showing the announcement |
| Timed quiz fails a student with a disability | Extendable/untimed attempt, warning, accommodation path | Config dump + test with extension granted |
| Error is only a red border | Text error + suggestion + focus management | Before/after screenshot + AT transcript |
| Certificate PDF is an image | Tagged PDF with real text; alt for seal; HTML alternative | PAC report + `pdftotext` output |
| Scanned worksheet is unreadable | Rebuild as tagged PDF or HTML; OCR + reading-order fix | PAC/veraPDF report |
| Outdated or paid government forms | Link to official `.gov` forms; do not rehost | Link audit + last-checked date |
| Marketing drifts into legal advice | Scope paragraph, script review, support macros | Published disclaimer + training log |
| "Accredited representative" claim | Precise wording; no implied EOIR authorization | Approved copy + counsel sign-off |
| Guaranteed results claim | Claims register; delete unsupported claims | Register row with evidence or removal |
| Government impersonation | No seals, explicit non-affiliation notice | Screenshot of footer + ad review |
| Fake or purchased reviews | Real reviews with permission and disclosure | Consent forms + review source log |
| Meta pixel fires before consent | Consent gate; tag audit; GPC | Tag manager screenshot + consent log |
| Real case data in exercises | Fictional, labeled cases; de-identification | Course content review + data map |
| Student data in ads/screenshots | Synthetic dashboards; blur/omit PII | Asset review + approval |
| Data kept forever | Retention schedule + deletion job | Policy + deletion log |
| EU student data sent to US without basis | SCCs/TIA or EU hosting; DPIA if needed | Signed SCCs + transfer assessment |
| Breach without a plan | Runbook, owner, templates, test | Runbook + tabletop record |
| Accessibility regression after an update | axe/Lighthouse in CI + manual smoke per release | CI artifact + dated smoke test |
| No proof of any of the above | Evidence archive per launch | `audit/<date>/report.md` |

---

## 9. Sources

Consulted 2026-11-13. Laws and figures change; re-check before relying on them.

**Standards and testing**

1. W3C, *Web Content Accessibility Guidelines (WCAG) 2.2*, W3C Recommendation, 5 Oct 2023 — https://www.w3.org/TR/WCAG22/
2. W3C WAI, *What's New in WCAG 2.2* — https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/
3. W3C WAI, *Understanding SC 1.2.2 Captions (Prerecorded)* — https://www.w3.org/WAI/WCAG22/Understanding/captions-prerecorded.html
4. W3C WAI, *Understanding SC 2.2.1 Timing Adjustable* — https://www.w3.org/WAI/WCAG22/Understanding/timing-adjustable.html
5. W3C WAI, *Understanding SC 3.3.4 Error Prevention (Legal, Financial, Data)* — https://www.w3.org/WAI/WCAG22/Understanding/error-prevention-legal-financial-data.html
6. W3C WAI, *Understanding SC 4.1.3 Status Messages* — https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html
7. W3C WAI, *WCAG 2.2 Conformance Requirements* — https://www.w3.org/TR/WCAG22/#conformance-reqs
8. ISO 14289-1 (PDF/UA-1); W3C, *PDF Techniques for WCAG 2.2* — https://www.w3.org/WAI/WCAG22/Techniques/pdf/
9. axe-core, rule descriptions and tags — https://github.com/dequelabs/axe-core/blob/develop/doc/rule-descriptions.md
10. Google Lighthouse, *Accessibility audits* — https://developer.chrome.com/docs/lighthouse/accessibility/

**US accessibility law**

11. ADA Title III, 42 U.S.C. § 12181 et seq.; 28 CFR Part 36 — https://www.ada.gov/law-and-regs/
12. U.S. DOJ, *Fact Sheet: New Rule on the Accessibility of Web Content and Mobile Apps Provided by State and Local Governments* (Title II; WCAG 2.1 AA, dates extended 2026) — https://www.ada.gov/resources/2024-03-08-web-rule/
13. Section 508, 29 U.S.C. § 794d — https://www.access-board.gov/ict/
14. California Unruh Civil Rights Act, Cal. Civ. Code § 51; New York State Human Rights Law, N.Y. Exec. Law § 296 (state-law vectors; confirm with counsel)

**Example: immigration practice and UPL**

15. 8 CFR § 1292.1 (Representation of others) — https://www.ecfr.gov/current/title-8/section-1292.1
16. 8 CFR § 1292.11 (Recognition of an organization) — https://www.ecfr.gov/current/title-8/chapter-V/subchapter-B/part-1292/subject-group-ECFR34e832807475ad1/section-1292.11
17. 8 CFR § 1292.12 (Accreditation of representatives); EOIR, *Recognition and Accreditation Program FAQ* — https://www.justice.gov/eoir/recognition-and-accreditation-program-frequently-asked-questions
18. EOIR, *Can Someone Represent You Before EOIR?* — https://www.justice.gov/eoir/can-someone-represent-you-eoir
19. California Business and Professions Code §§ 22440–22449 (Immigration Consultants Act); California Secretary of State, *Immigration Consultant Qualification Requirements* — https://www.sos.ca.gov/business-programs/special-filings/immigration-consultant-qualifications
20. New York Attorney General, *Immigration Services Fraud* — https://ag.ny.gov/publications/immigration-services-fraud

**Advertising and consumer protection**

21. FTC Act § 5, 15 U.S.C. § 45 — https://www.ftc.gov/legal-library/browse/statutes/federal-trade-commission-act
22. FTC, *Guides Concerning the Use of Endorsements and Testimonials in Advertising*, 16 CFR Part 255 — https://www.ecfr.gov/current/title-16/part-255
23. FTC, *Rule on the Use of Consumer Reviews and Testimonials*, 16 CFR Part 465, effective 21 Oct 2024 — https://www.ecfr.gov/current/title-16/chapter-I/subchapter-D/part-465
24. FTC, *How To Avoid Immigration Scams and Get Real Help* — https://consumer.ftc.gov/articles/how-avoid-immigration-scams-and-get-real-help
25. FTC, *Scams Against Immigrants* (notarios, fake government sites, paying for free forms) — https://consumer.ftc.gov/media/79858
26. FTC, *CAN-SPAM Act: A Compliance Guide for Business* — https://www.ftc.gov/business-guidance/resources/can-spam-act-compliance-guide-business

**Privacy**

27. California Consumer Privacy Act / CPRA, Cal. Civ. Code § 1798.100 et seq.; California Privacy Protection Agency thresholds ($26,625,000 from 1 Jan 2025; 100,000 consumers/households; 50% revenue) — https://cppa.ca.gov/faq.html
28. California Attorney General, *CCPA* — https://oag.ca.gov/privacy/ccpa
29. Regulation (EU) 2016/679 (GDPR), Arts. 5, 6, 9, 13–20, 30, 32, 35, 44 — https://eur-lex.europa.eu/eli/reg/2016/679/oj
30. Directive 2002/58/EC (ePrivacy), Art. 5(3) — https://eur-lex.europa.eu/eli/dir/2002/58/oj
31. COPPA, 15 U.S.C. § 6501 et seq. — https://www.ftc.gov/legal-library/browse/statutes/childrens-online-privacy-protection-act
32. FERPA, 20 U.S.C. § 1232g; 34 CFR Part 99 — https://www.ecfr.gov/current/title-34/part-99

**Education and EU accessibility**

33. California Private Postsecondary Education Act of 2009, Cal. Bus. & Prof. Code § 94800 et seq.; BPPE — https://www.bppe.ca.gov/
34. New York State Education Department, BPSS curriculum requirements — https://www.acces.nysed.gov/bpss/
35. Directive (EU) 2019/882 (European Accessibility Act), applicable 28 June 2025; EN 301 549 — https://eur-lex.europa.eu/eli/dir/2019/882/oj
36. European Commission, *European accessibility act* (microenterprise exemption: <10 employees and ≤€2M) — https://ec.europa.eu/social/main.jsp?catId=1202
