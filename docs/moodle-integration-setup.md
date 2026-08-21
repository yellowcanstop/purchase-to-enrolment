# Moodle Integration Setup & Gotchas

Everything needed to get a WooCommerce order → Moodle enrolment working, plus the
failure modes hit along the way. Each Moodle error below is the *literal* error
you get when the corresponding item is missing.

---

## 1. Service account capabilities

All capabilities must be **Allow** on the role assigned to the service account
(`woocommerce-sa`, role `webservicerestuser`), at **system context**.

Site administration → Users → Permissions → Define roles → *(role)*

| Capability | Needed by | Error if missing |
|---|---|---|
| `webservice/rest:use` | All calls | `accessexception` |
| `moodle/webservice:createtoken` | Token creation | — |
| `moodle/user:viewdetails` | `core_user_get_users_by_field` | lookup returns `[]` |
| `moodle/user:viewalldetails` | `core_user_get_users_by_field` | lookup returns `[]` |
| `moodle/site:viewuseridentity` | **Email lookup specifically** | lookup by email returns `[]` — *silently* |
| `moodle/user:create` | `core_user_create_users` | `nopermissions` — "(Create users)" |
| `moodle/course:view` | `enrol_manual_enrol_users` | `requireloginerror` — "Course or activity not accessible. (Not enrolled)" |
| `enrol/manual:enrol` | `enrol_manual_enrol_users` | `nopermissions` |
| `moodle/role:assign` | `enrol_manual_enrol_users` (assigns the role) | `nopermissions` |

> `moodle/user:viewdetails` and `moodle/user:viewalldetails` — Moodle's
> `can_view_user_details_cap()` accepts *either*, so one may suffice. Both were
> granted here; not worth the risk of narrowing.

### The subtle one: `moodle/site:viewuseridentity`

Worth calling out because it fails **silently and misleadingly**.

`core_user_get_users_by_field` ends with
([user/externallib.php](https://github.com/moodle/moodle/blob/main/user/externallib.php)):

```php
// Return the user only if the searched field is returned.
// Otherwise it means that the $USER was not allowed to search the returned user.
if (!empty($userdetails) and !empty($userdetails[$field])) {
```

Moodle strips `email` from user details unless the caller can see identity
fields. So searching by `email` returns `[]` — not an error, just empty — even
though the user exists and lookup by `id` or `username` works fine.

Symptom: the app concludes no user exists, calls `core_user_create_users`, and
gets `invalidparameter` / *"Username already exists"*. **The reported error is
two steps downstream of the actual cause.**

Requires both:
- capability `moodle/site:viewuseridentity`, **and**
- `showuseridentity` (Site admin → Users → Permissions → User policies) includes `email` (default).

---

## 2. Role assignment — context matters

- Assign the role at **system** context: Site administration → Users →
  Permissions → **Assign system roles**.
- A `manager` assignment at *course* context (including course id 1, the front
  page) does **not** grant system-context capabilities. This is easy to get
  wrong because the front page looks like "the whole site" in the UI.

## 3. Allow role assignments

Site administration → Users → Permissions → Define roles → **"Allow role
assignments"** tab → tick where row `webservicerestuser` meets column `student`.

Without it, `enrol_manual_enrol_users` throws `wsusercannotassign` — the
capability `moodle/role:assign` is necessary but **not sufficient**. Moodle also
consults `mdl_role_allow_assign`.

## 4. External service function allowlist

Site administration → Server → Web services → External services → *(service)* → **Functions**

Required:

- `core_user_get_users_by_field`
- `core_user_create_users`
- `core_enrol_get_users_courses`
- `enrol_manual_enrol_users`
- `core_webservice_get_site_info` *(not used by the app, but invaluable for debugging — see below)*

Missing function → `accessexception` / "Access control exception".

> Beware near-miss names in the picker: `core_user_get_users` is **not**
> `core_user_get_users_by_field`, and `core_enrol_get_enrolled_users` is **not**
> `core_enrol_get_users_courses`.

## 5. Course-level settings

- **Manual enrolment method must be enabled** on the target course:
  Course → Participants → Enrolment methods.
- **Send course welcome message: No** — Course → Participants → Enrolment
  methods → Manual enrolments → edit.

  Why: Moodle performs the enrolment, *then* sends the welcome message. With no
  mail transport configured (typical in Docker), `message_send()` throws
  `error/Message was not sent.` **after the enrolment has already committed.**
  The API call appears to fail while the enrolment actually succeeded — a false
  negative. Alternatively set `$CFG->noemailever = true` for local dev, or
  configure SMTP/mailcatcher.

  This is why `isEnrolled()` is checked before enrolling: on retry the job
  converges instead of double-enrolling or staying stuck.

- Role id **5 = student** on a default Moodle install (verify: Site admin →
  Users → Define roles).

---

## 6. Laravel-side gotchas

| Gotcha | Symptom |
|---|---|
| Webhook header name must match on both sides (`x-wc-webhook-signature`) | `401 Invalid signature` |
| `WEBHOOK_SECRET` unset → both sides HMAC over `null`, "matching" for the wrong reason | signature passes but proves nothing |
| WooCommerce sends `id`; app uses `order_id`. Normalise in `prepareForValidation()` | `422 The order id field is required` |
| Anything reading the stored payload must use the **normalised** key (`order_id`), since `validated()` only returns validated keys | `Undefined array key "id"` |
| Moodle config is top-level `config('moodle.*')`, **not** `services.moodle.*` | `TypeError: $token must be of type string, null given` |
| `MOODLE_BASE_URL` is the **site root**; `MoodleClient` appends `/webservice/rest/server.php` | doubled path → 404 |
| `env('KEY=')` (blank) returns `''`, not `null` — guard with `filled()`, not `??` | null-check passes, fails later confusingly |
| Enum-cast columns need enum comparison: `$r->status === EnrolmentStatus::Enrolled`, never `=== 'enrolled'` | guard silently always false; idempotency checks dead |
| Run `php artisan db:seed` — an empty `product_course_map` looks like a mapping bug | "No active course mapping for product" |
| Editing a migration that already ran does **not** change the live table | `Unknown column ... in 'field list'` |

### Queue workers cache code

`queue:work` loads classes once at boot. **Editing a job or any class it uses has
no effect on a running worker.** Restart it (`php artisan queue:restart`, or
Ctrl+C and relaunch) after every code change, or you will debug a fix that isn't
running.

Config changes need this too — the `MoodleClient` singleton is built at boot.

---

## 7. Debugging techniques that actually worked

**Moodle returns HTTP 200 for errors.** Always inspect the body for
`{"exception": ..., "errorcode": ..., "message": ...}`.

**Turn on `debuginfo`** — Site administration → Development → Debugging →
DEVELOPER + "Display debug messages". Moodle then returns a `debuginfo` field
naming the actual rejected value ("Username already exists: ..."). Without it you
get only "Invalid parameter value detected", which says nothing. Turn it off
afterwards.

**Log *which* `wsfunction` failed.** An error code alone can't distinguish four
different Moodle calls. `MoodleApiException` carries `$function` and
`$requestParams`; both are recorded in `enrolment_attempts`.

**`core_webservice_get_site_info` lists the token's allowed functions** — the
fastest way to confirm what the token can actually call:

```bash
curl -s -d "wstoken=$TOKEN&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json" \
  http://localhost:8080/webservice/rest/server.php
```

**Isolate Moodle from the app with curl.** Bypasses the queue entirely.
Use `--data-urlencode` — an unencoded `@` in `values[0]=user@example.com` gets
mangled by the shell and produces a misleading empty result:

```bash
curl -s --data-urlencode "wstoken=$TOKEN" \
     --data-urlencode "wsfunction=core_user_get_users_by_field" \
     --data-urlencode "moodlewsrestformat=json" \
     --data-urlencode "field=email" \
     --data-urlencode "values[0]=student@example.com" \
     http://localhost:8080/webservice/rest/server.php
```

**Compare lookup by `id` vs `email`.** If `id` works and `email` returns `[]`,
it's the identity-field permission (§1), not visibility or a missing user.

**Query Moodle's DB directly** when the API is being coy:

```bash
docker exec moodle-docker-db-1 sh -c \
  'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" moodle -e "SELECT id, username, email, deleted, suspended, confirmed FROM mdl_user WHERE email=\"student@example.com\"\G"'
```

Useful tables: `mdl_user`, `mdl_role_assignments`, `mdl_role_capabilities`,
`mdl_role_allow_assign`, `mdl_user_enrolments`, `mdl_enrol`, `mdl_config`.

Context levels: `10` = system, `50` = course.

---

## 8. Error → cause quick reference

| Moodle error | Cause |
|---|---|
| `accessexception` | wsfunction not on the external service's allowlist |
| `nopermissions` | Function allowed, but role lacks the capability. Message names it: "(Create users)" |
| `requireloginerror` — "Course or activity not accessible" | Missing `moodle/course:view` |
| `wsusercannotassign` | Missing entry in "Allow role assignments" |
| `invalidparameter` — "Username already exists" | User exists but lookup couldn't see them → check `moodle/site:viewuseridentity` |
| `error/Message was not sent.` | Welcome email failed **after** a successful enrolment. Disable welcome message |
| Lookup returns `[]`, no error | Searched field stripped from user details by permissions |
