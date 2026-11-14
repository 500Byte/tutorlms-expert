# Tutor LMS REST API

> **Read when:** you are calling or extending Tutor's REST namespace and need routes, auth or version limits.
>
> Sources: [Introduction to REST API](https://tutorlms.com/docs/developer-guides/introduction-to-rest-api/) and [REST API: Tutor LMS (Free)](https://tutorlms.com/docs/developer-guides/free-rest-apis/), read 2026-11-13. Version-gated: token auth requires **Tutor LMS 4.2+**.

The API is a WordPress REST namespace: `https://SITE/wp-json/tutor/v1/`. Discover routes on the live site instead of trusting this page when they disagree:

```bash
curl -s https://SITE/wp-json/tutor/v1 | jq '.routes | keys'
```

## What Free vs Pro exposes

| Capability | Free | Pro |
|---|---|---|
| Auth (`/auth/login`, `/auth/refresh`, `/auth/logout`) | yes | yes |
| Read courses, curriculum, quizzes, authors, ratings, attempts | yes | yes |
| Create/update/delete (enrollments, assignment submissions, quiz attempts, Q&A) | no | yes |

Free is read-oriented. If a task needs a write route, confirm the Pro license before designing around it; otherwise fall back to WP-CLI/PHP (see `data-model.md`).

## Auth model (changed in 4.2.0)

Two credentials, two different jobs:

| Credential | Identifies | Sent |
|---|---|---|
| API key + secret | Your application and its permission level (`Read`, `Write`, `Delete`, `All`) | **Only** to `/auth/login`, as headers |
| Access token | The end user | Every other request, as `Authorization: Bearer` |
| Refresh token | A renewable session tied to the API key | `/auth/refresh`, `/auth/logout` |

Old Basic Auth with key + secret on every request **no longer works**. Keys stopped acting as the key owner: responses now depend on the user the token belongs to. An admin token sees admin data; a student token sees only what that student may see.

Keys are created in **Tools > REST API > Add New**. Give the lowest permission level the job needs.

### Login

```bash
curl -s -X POST https://SITE/wp-json/tutor/v1/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Tutor-Api-Key: YOUR_API_KEY' \
  -H 'Tutor-Api-Secret: YOUR_API_SECRET' \
  -d '{"username":"END_USER_USERNAME","password":"END_USER_PASSWORD"}'
```

```json
{
  "access_token": "ACCESS_TOKEN",
  "expires_in": 86400,
  "refresh_token": "REFRESH_TOKEN",
  "user_id": 12,
  "display_name": "Jane Doe"
}
```

`username` accepts the WordPress login or the email. `expires_in` is seconds; `86400` is the 1-day default and `0` means never expires. A missing or invalid key returns `rest_forbidden`.

### Refresh

```bash
curl -s -X POST https://SITE/wp-json/tutor/v1/auth/refresh \
  -H 'Content-Type: application/json' \
  -d '{"refresh_token":"YOUR_REFRESH_TOKEN"}'
```

No API key headers needed. The response has the login shape with a **new** access and refresh token. Refresh tokens are bound to the key that issued them: if the key was deleted or revoked, the call returns 403.

### Logout

```bash
curl -s -X POST https://SITE/wp-json/tutor/v1/auth/logout \
  -H 'Content-Type: application/json' \
  -d '{"refresh_token":"YOUR_REFRESH_TOKEN"}'
# or, with a valid access token:
# -H 'Authorization: Bearer ACCESS_TOKEN' -d '{"all":true}'
```

`{ "success": true }`. Send `refresh_token` to end one session, or `all: true` plus a valid access token (`Authorization: Bearer` or `Tutor-User-Token`) to end every session for that user.

### Token lifetimes

Configured in **Tools > REST API > Token Lifetime**, in days:

| Token | Default | Range |
|---|---|---|
| Access | 1 day | 1–365 |
| Refresh | 30 days | 1–365 |

Refresh lifetime must exceed access lifetime. `0` disables expiry; do not ship that — a stolen token stays valid forever.

> **Idempotency warning:** every login creates a session, and refresh rotates the token pair. Store the new refresh token atomically before discarding the old one. Retrying a refresh with an already-rotated token fails, so treat a refresh as a single-use write, not a safe read.

## Response envelope

```json
{ "code": "success", "message": "Human-readable message", "data": {} }
```

- Successful requests return **200 OK**.
- Dates: `YYYY-MM-DD HH:MM:SS`.
- Resource IDs: integers.
- Money: strings with two decimals (`"49.00"`), not floats.
- Missing resource or empty collection can still be **200** with `"code": "not_found"` and empty `data`. Check `code`, not only the HTTP status.
- Payloads depend on the token's user (see below).

## Access rules that change the response

| Area | Rule |
|---|---|
| Course content (details, curriculum, topics, lessons, announcements, quizzes, answers, attempts) | 200 only if the course is public, the user is enrolled, or the user is an instructor/admin for that course. Otherwise **403**. |
| Private user fields | Default: `ID`, `display_name`, `nicename`. Login name, email, registration date and URL only when the caller is that user, has `list_users`, or instructs a course the user is enrolled in. Reviewer emails follow the same rule. |
| Quiz answers | Correct answers are stripped until the learner completes an attempt, unless the caller can manage the course. Choice options come without `is_correct`; other types return empty answers. |
| Quiz attempts | Only the caller's own, unless the caller can manage the course. |

A `403` is therefore usually **correct behavior**, not a broken token. Distinguish "token missing/expired" (401) from "this user may not see this" (403) before changing anything.

## Routes and requirements

From the docs' requirement table:

| Route | Method | Needs |
|---|---|---|
| `/auth/login` | POST | API key + secret (Read or higher) |
| `/auth/refresh` | POST | Refresh token |
| `/auth/logout` | POST | Refresh token, or access token with `all: true` |
| `/courses` | GET | Access token, Read |
| `/courses/{id}` | GET | Access token, Read, course content access |
| `/course-contents/{id}` | GET | Access token, Read, course content access |
| `/topics` | GET | Access token, Read, course content access |
| `/lessons` | GET | Access token, Read, access to the topic's course |
| `/course-announcement/{id}` | GET | Access token, Read, course content access |
| `/quizzes`, `/quizzes/{id}` | GET | Access token, Read, access to the quiz's course |
| `/quiz-question-answer/{id}` | GET | Access token, Read, access to the quiz's course |
| `/quiz-attempt-details/{id}` | GET | Access token, Read, access to the quiz's course |
| `/author-information/{id}` | GET | Access token, Read |
| `/course-rating/{id}` | GET | Access token, Read |

Free read catalogue (same source, second page):

| Purpose | Route | Params |
|---|---|---|
| List courses | `GET /courses` | `order`, `orderby`, `paged` (1-based), `tags=a,b`, `categories=c,d` |
| One course | `GET /courses/{course_id}` | required int |
| Curriculum tree | `GET /course-contents/{course_id}` | required int |
| Topics | `GET /topics?course_id={course_id}` | required int |
| Lessons | `GET /lessons?topic_id={topic_id}` | required int |
| Course announcements | `GET /course-annoucement/{course_id}` | required int |
| Quiz by topic | `GET /quiz/{topic_id}` | required int |
| Quiz questions | `GET /quiz-question-answer/{quiz_id}` | required int |
| Author profile | `GET /author-information/{author_id}` | required int |
| Course ratings | `GET /course-rating/{course_id}` | required int |

> **Doc inconsistency — verify before coding.** The two official pages disagree: the introduction lists `/course-announcement/{id}` and `/quizzes`, `/quizzes/{id}`; the Free page lists `/course-annoucement/{course-id}` (misspelled) and `/quiz/{topic-id}`. The route that exists is whatever `GET /wp-json/tutor/v1` returns. Hit the index or `OPTIONS` on the candidate route and use the registered path; do not "fix" the spelling blind.

## Curl recipes

```bash
# 1. List public courses, page 1
curl -s "https://SITE/wp-json/tutor/v1/courses?paged=1&orderby=date&order=desc" \
  -H "Authorization: Bearer $ACCESS" | jq

# 2. Full curriculum of one course
curl -s "https://SITE/wp-json/tutor/v1/course-contents/123" \
  -H "Authorization: Bearer $ACCESS" | jq '.data'

# 3. Enrolled student's view of a quiz (answers stripped until attempt completes)
curl -s "https://SITE/wp-json/tutor/v1/quiz-question-answer/456" \
  -H "Authorization: Bearer $ACCESS" | jq '.data'
```

## Errors

| HTTP | Code | Meaning |
|---|---|---|
| 400 | `INVALID_PARAM` | Missing or invalid parameters |
| 401 | `UNAUTHORIZED` | Access token missing, invalid, or expired |
| 403 | `FORBIDDEN` | Key permission or user access denies this resource/action; also a refresh token whose API key was revoked |
| 404 | `NOT_FOUND` | No such resource/route |
| 405 | `METHOD_NOT_ALLOWED` | Wrong HTTP verb |
| 500 | `INTERNAL_SERVER_ERROR` | Unexpected server condition |

Only 400/401/403/500 originate in Tutor LMS; WordPress handles the rest (408, 502, 504, 505, 507, 508). WordPress errors use `{ code, message, data: { status } }`; a bad key at login returns `rest_forbidden` with status 401.

## Debugging order

1. **401** — is the header `Authorization: Bearer`, not `Tutor-Api-Key`? Has the access token expired (`expires_in`)? Refresh. Never send the key as a Bearer token.
2. **403 on a course route** — check enrollment, `_tutor_is_public_course`, and the token's user role. A valid read key still cannot read a private course the user is not enrolled in.
3. **403 on refresh** — the API key was deleted or revoked; issue a new key and log in again.
4. **200 with `code: not_found`** — the route matched but the ID does not exist or is not visible; do not treat as success.
5. **Correct token, wrong data** — the token belongs to another user. Confirm `user_id` from login and re-check the access rules above.
6. **Route 404** — confirm the namespace and spelling via `GET /wp-json/tutor/v1`; the docs contain both spellings.

## Security

- Keep keys, secrets and refresh tokens server-side; never in theme JS, a public repo, or a log line.
- HTTPS only; the API works over HTTP but you should not.
- One key per integration, lowest permission, rotated on staff turnover.
- Treat the refresh token as a password: it mints new access tokens.
- Log out to revoke sessions; do not rely on expiry alone.
