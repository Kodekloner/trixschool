# ID Card Design Studio guide

Last reviewed: 20 September 2026

SchoolLift's ID Card Design Studio is a responsive, practical design workspace for student and staff cards. It preserves the existing ID-card templates and generation pages while letting an authorized administrator explicitly convert a template into a separate, versioned front/back design.

It is inspired by the workflow of desktop design tools such as CorelDRAW—canvas, layers, selection properties, rulers, snapping, alignment, history, and print controls—but it is deliberately limited to safe ID-card objects. It is not a general vector illustration program and does not execute arbitrary HTML or remote scripts.

## Access and permissions

The menus are under **Certificate**:

- **Student ID Design Studio**: `/admin/idcardstudio/index/student`
- **Staff ID Design Studio**: `/admin/idcardstudio/index/staff`

Viewing follows the existing `student_id_card` or `staff_id_card` view privilege. Conversion, editing, saving, uploading, and publishing require the corresponding edit privilege. Every modifying request is POST-only and carries the Studio session CSRF token.

If the page says migration 131 is missing, stop and install the tenant migration before attempting a conversion. Do not create tables manually for only one school.

The v2 editor upgrade requires **no new SQL migration, table alteration, or bulk database update**. It uses the Studio tables and JSON columns already installed by migration 131. Normal draft saves update the existing `schema_version` field.

## Legacy compatibility and conversion

The Studio list shows every existing student/staff ID template with one of these states:

- **Legacy only**: the ordinary template remains the active source and has no Studio copy.
- **Converted / Draft only**: a separate Studio design exists but has not been published.
- **Converted / Published**: an approved version exists and later edits continue in a separate draft.

To convert:

1. Back up the tenant database.
2. Open the correct Student or Staff Design Studio list.
3. Find the intended legacy template.
4. Click **Convert to Design Studio**.
5. Confirm the prompt.
6. Wait for the editor to open and inspect both Front and Back.

Conversion never overwrites the legacy row. It derives dimensions, orientation, title, colours, known fields, photograph, logo/signature, and any compatible personal-branch layout into a new versioned document. If conversion fails, the original template remains unchanged.

Existing templates continue working until the administrator publishes their Studio copy. After publication, the ordinary **Generate ID Card** or **Generate Staff ID Card** action automatically uses that published front/back design for the corresponding legacy template. If no published Studio version exists, generation remains on the unchanged legacy renderer. Keep the original until printed samples have been approved.

## Workspace layout

### Top toolbar

- Edit the design name.
- Select standard **CR80 Landscape** (`85.60 × 53.98 mm`) or **CR80 Portrait** (`53.98 × 85.60 mm`).
- Enter custom width/height from 40 to 220 mm.
- Undo or redo.
- See draft save state.
- Save a draft or publish an approved version.
- Restore the preserved legacy renderer with **Use legacy** after a Studio version has been published.

Changing card dimensions proportionally moves/resizes objects on both sides. Always recheck text, photograph crop, QR quiet zone, bleed, and safe area after changing size.

### Left panel

Add:

- static text;
- rectangle, rounded rectangle, ellipse, line, triangle, diamond, regular polygon, configurable star, and arrow;
- editable curves using Pen or Freehand;
- trusted attendance QR;
- Code128 barcode;
- allowlisted student/staff/school fields;
- PNG/JPEG artwork uploaded to the design.

Uploaded artwork is limited to a genuine PNG or JPEG under 5 MB. The server verifies image type and size, stores a controlled asset record, and serves it back through an authenticated same-origin URL so canvas export does not depend on an unrestricted external host.

### Centre workspace

- Switch between **Front** and **Back**.
- Toggle the 5 mm grid.
- Toggle movement snapping and centre snapping.
- Toggle the 3 mm safe-area guide.
- Use rulers, Fit, and 25–250% zoom (Ctrl/Cmd-wheel zooms around the pointer).
- Drag, resize, and rotate selected objects directly.
- Shift-click or drag-select multiple objects where the browser/pointer permits.
- Selection borders use the object's exact outer frame: handle centres sit on its edges without added selection padding.

Grid, rulers, and safe-area indicators are editor aids and are not printed.

### Right panel

For a selected object, edit its safe object name, position/size in millimetres, rotation, opacity, text/binding, font, size, colour, alignment, bold, and italic values where applicable.

You can also:

- duplicate, lock, or delete the selected object;
- hide/show and lock/unlock layers;
- move a layer forward/backward;
- align to the card, selection bounds, or a reference object;
- configure exact-card/A4 export, margins, gaps, crop marks, and duplex pages;
- inspect version history.

## Dynamic student and staff fields

The server accepts only these known bindings.

Common:

- school name and address;
- school logo and authorized signature;
- card title;
- trusted attendance credential for QR/Code128.

Student:

- full name;
- admission number;
- class and section;
- father/mother name;
- address and phone;
- date of birth and blood group;
- photograph.

Staff:

- full name and employee ID;
- role, department, and designation;
- father/mother name;
- joining date;
- address and phone;
- date of birth;
- photograph.

The canvas uses safe sample values while editing so the administrator can judge spacing. A binding is not raw PHP/SQL/HTML: the server validates it against the list for the chosen subject type.

## Build a student card

1. Convert a suitable Student ID template.
2. Choose CR80 portrait or landscape before detailed alignment.
3. Open **Front**.
4. Place the school name/logo and card title inside the safe area.
5. Add the student photograph binding and size it for a clear face.
6. Add name, admission number, class/section, and any approved fields.
7. Add the trusted attendance credential as QR. Keep a clear quiet zone around it and avoid placing it over a patterned background.
8. Add a short printed identifier below the QR if the school needs manual recovery.
9. Open **Back**.
10. Add return instructions, address/contact, signature, and approved privacy wording.
11. Save the draft and export a measured sample.
12. Scan the printed QR through the authenticated gate scanner in simulation mode.
13. Obtain school approval, then publish.

Do not encode a public attendance URL, student medical/private notes, database ID, session token, or biometric template into the card.

## Build a staff card

Use the Staff Design Studio and follow the same workflow. Prefer employee ID, role/designation, department, photograph, school branding, expiry/return wording where the school requires it, and the trusted attendance credential.

Keep student and staff designs separate. A student-only binding is rejected from a staff document and vice versa.

## Object and layer workflow

1. Add an object from the left panel.
2. Give it a meaningful unique name such as `student-name` or `back-return-address`.
3. Set exact X/Y/width/height in millimetres for repeatable alignment.
4. Use snapping for normal placement and turn it off temporarily for fine movement.
5. Align objects using left/centre/right/top/middle/bottom/centre-both controls in Arrange or Actions.
6. Reorder overlapping objects from Layers.
7. Lock the background, header, and approved branding before editing variable fields.
8. Hide a layer only when it should remain in the design but not render; delete it if it should no longer exist.

Locked objects cannot move, resize, rotate, or be deleted. They can be selected as alignment references, including through Layers. Hidden objects are excluded from output.

### Boundaries, frames, and history

Objects and visible outlines stay inside the physical card, whether snapping is on or off. Dragging stops at an edge; resizing stops at its largest valid anchored size; invalid rotations retain the last valid angle. Paste fits the entire copied selection proportionally. X/Y fields represent the **centre** of an object, in millimetres. Numeric fields apply on commit (Tab, Enter, or leaving the field), so partially typed values are not rewritten while typing.

Groups move and fit as units. Card-size changes affect both sides and can be undone. A resize that cannot retain valid minimum object sizes is declined with guidance. Text uses fixed frames and automatically fits long real names inside them. Shadows can extend beyond a frame but are clipped at the card edge. Background images fill the card.

Undo/redo covers both sides, card dimensions, print settings, and individual gestures/commands. Published versions are separate from this editing history.

### Reference alignment and object actions

Select A, then hold Shift and select B: **B is the reference**, indicated in amber and by “Align to: B.” Alignment moves A while B stays fixed. Change the target to Card or Selection bounds in Arrange when needed. Use **Set as reference** in Actions or the ◎ button beside a layer to choose another reference, including a locked layer.

Existing groups count as one alignment/distribution unit. Distribution needs at least three units and preserves the outermost positions. An alignment that would cross the card edge or move a locked object is rejected without moving the selection.

Right-click an object for copy, cut, paste, duplicate, delete, grouping, locking, layer order, alignment, style copying, and applicable curve commands. Right-click within a selection preserves it. Use arrow keys and Enter in the menu, or Escape to close it. On touch screens use **Multi-select** and **Actions**; long-press also opens object actions. **Commands** searches the available commands; **?** opens shortcut help.

### Shape appearance and curves

Properties includes polygon sides, star points/inner radius, arrow head/shaft proportions, and rectangle corner radius. Shapes, text, and photographs support opacity, flips, solid/transparent fills where applicable, outlines, and drop shadows. QR/barcode colour handling remains separate.

Select a geometric shape and choose **Convert to curves**. Conversion preserves its appearance and is undoable. Text and photographs keep their normal controls; they are not converted to outlines.

Press **N** on a path to edit nodes. Orange identifies the selected node; blue nodes and white Bézier handles can be dragged within the fixed path frame. Enlarge the frame first if you need more drawing room. Actions or Properties provides insert/delete, smooth/corner nodes, straight/curved following segments, and open/close path. Inserting splits the following segment; an open path's last node has no following segment. Smooth handles remain tangent; corner handles move independently. At least two and at most 256 nodes are allowed. Node handles use the bundled Fabric.js custom-controls API ([official example](https://fabric5.fabricjs.com/custom-controls-polygon)), with fixed local frames to avoid anchor drift.

**Pen (P):** click corners, or drag as you place a point to create curved handles. **Freehand:** draw one continuous stroke; it is simplified to an editable path. Enter/Finish completes either operation; Escape/Cancel discards the unfinished path. Finish or cancel before saving. Shapes cannot execute arbitrary SVG/HTML. Text outlines, photo tracing, boolean shape operations, and CorelDRAW file import are not included.

## Keyboard shortcuts

| Action | Shortcut |
| --- | --- |
| Undo | `Ctrl/Cmd + Z` |
| Redo | `Ctrl/Cmd + Y` or `Ctrl/Cmd + Shift + Z` |
| Save draft | `Ctrl/Cmd + S` |
| Duplicate | `Ctrl/Cmd + D` |
| Copy / cut / paste selection | `Ctrl/Cmd + C / X / V` |
| Select all | `Ctrl/Cmd + A` |
| Group / ungroup | `Ctrl/Cmd + G` / `Ctrl/Cmd + Shift + G` |
| Select / pan | `V` / `H`; hold Space to pan temporarily |
| Text / rectangle / ellipse | `T` / `R` / `O` |
| Pen / node edit | `P` / `N` |
| Finish drawing / cancel | `Enter` / `Escape` |
| Search commands / shortcut help | `Ctrl/Cmd + K` / `?` or `F1` |
| Object actions | Right-click or `Shift + F10` |
| Fit design | `Ctrl/Cmd + 0` |
| Delete | `Delete` or `Backspace` |
| Move selected object | Arrow key, 1 mm |
| Move selected object faster | `Shift` + arrow key, 5 mm |

Shortcuts do not intercept normal typing while focus is in an input, textarea, select, or editable text control. Save commits the focused input first. Open command menus handle their own keyboard navigation.

## Drafts, autosave, conflicts, and publishing

- A changed draft autosaves after about 2.5 seconds of inactivity.
- Edits during an in-flight save remain pending and are saved using the latest successful checksum. Autosave waits for active gestures/drawing to finish; a failed or conflicting save does not clear pending changes.
- **Save draft** forces an immediate save.
- Manual Save and Publish finish a valid in-progress path, discard an incomplete one-point path, correct recoverable object/data errors, and retry one unexpected validation failure. Temporary network or server failures are retried automatically; retries are idempotent if the server committed before its response was lost.
- The browser warns before leaving with unsaved changes.
- The server checks an expected checksum. If another browser saved first, it returns a conflict rather than overwriting that work. Reload, compare, and reapply the intended change.
- Publishing first saves the draft, requests confirmation, archives the previous published version, marks the approved version published, and opens a new draft version for later changes.
- **Use legacy** is an authenticated, audited rollback action. It archives the current published Studio version, clears it from future generation, retains every Studio version/draft, and returns future cards to the unchanged legacy template.
- Version history identifies draft, published, and archived revisions.

Publishing is an approval action. Give edit/publish permission only to designated staff, and print/sign off a physical sample before adopting a changed design.

## QR credentials and card replacement

The `attendance.credential` object represents a random, revocable SchoolLift credential. It must never be a GET URL that marks attendance.

Before issuance or reprinting, the SchoolLift server must have `BIOMETRIC_QR_ENCRYPTION_KEY` set to a random value of at least 32 characters in the protected PHP/web-service environment. Store and back it up in the organization's encrypted secret vault, outside Git and the web root. Restart PHP/web services and confirm the QR Scanner tab no longer reports that credential issuance is unavailable. The application intentionally refuses issuance when the key or required OpenSSL functions are missing.

Changing or losing this key prevents decryption of existing credential values for reprinting. Do not rotate it as an ordinary password. Use a tested decrypt-old/re-encrypt-new procedure when one is available; otherwise retain the protected original key or plan to revoke and reissue every affected card. Database backup alone is not sufficient QR reprint recovery—the matching environment key must also be recoverable.

For issuance:

1. Generate/activate a credential for the student or staff member.
2. Generate the person's card from the published design. The server supplies the active encrypted credential token only to the authorized generation response, and the browser renders it locally.
3. Print and test it with the authenticated gate scanner in `simulation`.
4. Verify the scanner displays the correct photograph/name before accepting IN/OUT.
5. Record issuance according to school policy.

For a lost/stolen/damaged card:

1. Revoke the existing credential immediately.
2. Verify scans of the old card are rejected.
3. Issue a new credential and card.
4. Audit who revoked/reissued it and why.

A photograph/copy of a QR may still be presented. The gate operator's visual photo comparison and supervised scanner are required controls.

## Preview, export, and print

The editor uses the same canonical object renderer for both sides and for these outputs:

- **300-DPI PNG** of the selected side;
- **Exact PDF** whose page dimensions match the card;
- **A4 grid PDF** using configured margin/gap, optional crop marks, and optional front/back duplex pages;
- browser print of the selected side at exact millimetre size.

Before printing:

1. Save the draft.
2. Set card dimensions first.
3. Check every object remains inside the safe area unless it intentionally bleeds.
4. Use PNG with sufficient source-image resolution; a small web logo cannot become sharp merely because output is 300 DPI.
5. For exact output, disable printer/browser `Fit`, `Shrink`, or `Scale to page`; use **100% / Actual size**.
6. Print on plain paper first and measure `85.60 × 53.98 mm` with a ruler/caliper.
7. Overlay front/back test sheets against a light source before using card stock.
8. Confirm the printer's long-edge/short-edge duplex behavior with one sheet. Driver interpretation varies.
9. Scan every approved QR/barcode sample at the real gate distance/light.

A4 grid export inside the editor repeats the currently rendered sample card to validate layout and cutting/duplex alignment. Do not issue a card containing the editor's sample person.

### Generate real student/staff cards

1. Save and publish the approved Student or Staff Studio design.
2. Open the existing **Generate ID Card** or **Generate Staff ID Card** page.
3. Select the corresponding original template and the intended real records using the normal class/section or staff filters.
4. Preview the selection and keep each generation request at **250 unique records or fewer**. Duplicate submitted IDs are removed and invalid/inaccessible records are rejected.
5. Start generation and allow the print popup. It waits for the local Fabric/QR renderer and same-origin photographs/assets to finish before exposing Print; do not close it while it says it is rendering.
6. Verify several names, photographs, admission/employee IDs, front/back pages, and QR credentials before printing the batch.
7. Print at Actual size and retain the approved batch/issuance evidence.
8. Generate the next group separately when more than 250 cards are required.

Real generation resolves each selected SchoolLift record into the same allowlisted bindings used by the editor. It uses the published version only, never an unsaved draft. Photographs and design assets use authenticated same-origin paths, and trusted QR tokens are generated locally rather than sent to an external QR service.

If the chosen template has no published Studio design, the existing legacy generation output remains unchanged. To roll back after publication, open that design, click **Use legacy**, confirm the warning, and verify the list reports **Draft only** before generating again. The published version is archived, not deleted, so the administrator can correct the retained draft and publish a new approved version later.

## Responsive use

The workspace fits the device viewport. Save, Undo/Redo, side switching, Actions, and zoom stay accessible while only the design area and open panels scroll. The inspector has Properties, Layers, Arrange, and Output tabs. On narrow screens, tools/properties use drawers or bottom sheets with a persistent Close control; the document page itself does not scroll.

For complex work—many layers, multi-selection, precise alignment, detailed text, or duplex setup—use a keyboard/mouse and a desktop/tablet. On a phone, prefer review, simple property changes, movement, save, and emergency export. Always reopen the final design at desktop width before publication.

## Security and data rules

- Schema v2 stores allowlisted text, geometric shapes, bounded vector paths, images, QR, and barcode objects in existing JSON columns. Geometry is in millimetres, with centre-origin positions and flattened temporary selection transforms.
- Existing published v1 designs retain their original renderer. Opening an old draft adapts it in memory; saving adopts v2. Only Publish makes that draft active for generation.
- Editable paths are limited to 256 normalized nodes with bounded Bézier handles; whole sides retain the 150-object and 256 KiB limits.
- Documents are size/object-count bounded and canonicalized server-side.
- Arbitrary HTML, JavaScript, provider URLs, unknown bindings, unsupported Fabric objects, and unsafe colours/values are rejected.
- Assets require permission and are proxied from approved storage through the school origin.
- Local pinned Fabric.js, QR, JsBarcode, and jsPDF files prevent third-party runtime/CDN dependence.
- The QR library receives only the credential selected for output; no external QR image API sees it.
- Optimistic checksum conflicts prevent silent last-writer overwrite.
- Conversion, saves, uploads, and publishing are auditable/versioned.

Do not upload unlicensed fonts/artwork or private source documents that do not belong on an ID card.

Deploy the PHP validator/model/controller changes, editor/runtime views, and all three Studio browser modules together. The views use versioned asset URLs so stale scripts cannot silently drop v2 properties. Do not deploy the v2 editor to a server running the v1 validator.

## Automated verification

Run from the repository root:

```sh
php tools/id-card-studio/tests/run.php
node tools/id-card-studio/tests/geometry.cjs
php tests/id_card_print_image_contract_test.php
php tests/school_media_url_test.php
```

Browser coverage uses the real editor, runtime renderer, and PHP validator with synthetic records, without bootstrapping the application or connecting to a database. Install Playwright in a disposable directory (or use an existing installation), start the local-only fixture, then run the browser suite in another terminal:

```sh
STUDIO_BROWSER_TESTS=1 php -S 127.0.0.1:8765 tools/id-card-studio/tests/browser-router.php
PLAYWRIGHT_MODULE=/absolute/path/to/node_modules/playwright node tools/id-card-studio/tests/browser.cjs
```

Use `CHROMIUM_EXECUTABLE=/absolute/path/to/chrome` for an existing compatible Chromium binary, and `STUDIO_TEST_URL` to change the fixture URL. The fixture refuses requests outside PHP's development server or without its explicit test environment flag. Never point this suite at a school account.

## Acceptance test

For both a student and staff design:

1. Convert a legacy landscape template and prove its original still renders.
2. Convert/test a portrait/personal-layout template if present.
3. Add each supported object type and representative bindings.
4. Move, resize, rotate, duplicate, hide, lock, reorder, align, copy/paste, and delete.
5. Test front/back switching and custom dimensions.
6. Perform over 100 changes and verify undo/redo remains bounded and responsive.
7. Verify autosave/reload, explicit save, two-browser conflict, publish, **Use legacy** rollback, archived history, and continued draft editing.
8. Reject an invalid binding/object/oversized or fake image/expired CSRF/direct GET mutation.
9. Export PNG, exact PDF, A4 crop marks, duplex pages, and browser print.
10. Measure CR80 output and test front/back alignment.
11. Render actual student/staff records through generation, confirm a batch over 250 is rejected/capped, verify deduplication, images/fields/front/back, and scan trusted credentials.
12. Repeat key operations at desktop, tablet, and phone widths.

## Troubleshooting

| Symptom | Check | Resolution |
| --- | --- | --- |
| Studio menu missing | ID-card module/role permission | Enable module and grant only required privilege |
| Migration warning | Tenant database migration version/tables | Apply migration 131 through the normal all-school process |
| No Open Studio button | Template is Legacy only | Use Convert to Design Studio with edit permission |
| Save conflict | Another tab/user changed the checksum | Reload; do not repeatedly force stale saves |
| Save/Publish keeps retrying or fails | Temporary server/network problem, expired session, permission/CSRF failure, or a real version conflict | Let automatic retries finish, then restore connectivity or session access; security and conflict checks are never bypassed |
| Image upload rejected | Type, actual file signature, 5 MB limit | Use a genuine optimized PNG/JPEG |
| Image previews but export fails | Storage/CORS/session/asset permission | Check authenticated same-origin asset response and S3 URL configuration |
| QR issuance unavailable | Server QR encryption key/OpenSSL missing | Configure and securely back up `BIOMETRIC_QR_ENCRYPTION_KEY` (32+ random characters), restart PHP/web service; never bypass the check |
| Existing QR renders blank after restore | The database was restored without its matching QR encryption key | Restore the exact protected key or revoke/reissue affected credentials |
| QR does not scan | Too small, low contrast, patterned background, print scaling | Enlarge it, use dark-on-light, preserve quiet zone, print Actual size |
| Card size is wrong | Browser/printer fitting enabled | Disable fit/shrink and measure a 100% sample |
| Duplex back is mirrored/wrong edge | Driver flip convention | Test one paper sheet and select correct printer edge/orientation |
| Text overflows with real name | Sample was shorter than real data | Test long Nigerian names/IDs; enlarge box or reduce approved font size |
| Phone is difficult for layers | Limited viewport/pointer precision | Finish complex work on desktop/tablet |
| Published output seems old | Wrong template/version selected or draft not published | Verify list status, published version, and generation selection |

## Rollback

If a Studio design is not approved, continue using the untouched legacy template. Do not delete legacy rows. Preserve Studio version/audit records for investigation, correct the draft, publish a new version, and retest a physical sample before changing production generation.
