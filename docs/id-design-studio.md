# ID Card Design Studio guide

Last reviewed: 12 August 2026

SchoolLift's ID Card Design Studio is a responsive, practical design workspace for student and staff cards. It preserves the existing ID-card templates and generation pages while letting an authorized administrator explicitly convert a template into a separate, versioned front/back design.

It is inspired by the workflow of desktop design tools such as CorelDRAW—canvas, layers, selection properties, rulers, snapping, alignment, history, and print controls—but it is deliberately limited to safe ID-card objects. It is not a general vector illustration program and does not execute arbitrary HTML or remote scripts.

## Access and permissions

The menus are under **Certificate**:

- **Student ID Design Studio**: `/admin/idcardstudio/index/student`
- **Staff ID Design Studio**: `/admin/idcardstudio/index/staff`

Viewing follows the existing `student_id_card` or `staff_id_card` view privilege. Conversion, editing, saving, uploading, and publishing require the corresponding edit privilege. Every modifying request is POST-only and carries the Studio session CSRF token.

If the page says migration 131 is missing, stop and install the tenant migration before attempting a conversion. Do not create tables manually for only one school.

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
- rectangle, ellipse, and line;
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
- Use rulers and 50–250% zoom.
- Drag, resize, and rotate selected objects directly.
- Shift-click or drag-select multiple objects where the browser/pointer permits.

Grid, rulers, and safe-area indicators are editor aids and are not printed.

### Right panel

For a selected object, edit its safe object name, position/size in millimetres, rotation, opacity, text/binding, font, size, colour, alignment, bold, and italic values where applicable.

You can also:

- duplicate, lock, or delete the selected object;
- hide/show and lock/unlock layers;
- move a layer forward/backward;
- align one object to the card or align a multi-selection to its bounds;
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
5. Align objects using the six alignment buttons.
6. Reorder overlapping objects from Layers.
7. Lock the background, header, and approved branding before editing variable fields.
8. Hide a layer only when it should remain in the design but not render; delete it if it should no longer exist.

Locked objects cannot be selected/moved on canvas until unlocked from Layers. Hidden objects are excluded from output.

## Keyboard shortcuts

| Action | Shortcut |
| --- | --- |
| Undo | `Ctrl/Cmd + Z` |
| Redo | `Ctrl/Cmd + Y` or `Ctrl/Cmd + Shift + Z` |
| Save draft | `Ctrl/Cmd + S` |
| Duplicate | `Ctrl/Cmd + D` |
| Copy/paste one object | `Ctrl/Cmd + C`, then `Ctrl/Cmd + V` |
| Delete | `Delete` or `Backspace` |
| Move selected object | Arrow key, 1 mm |
| Move selected object faster | `Shift` + arrow key, 5 mm |

Shortcuts do not intercept normal typing while focus is in an input, textarea, or select control.

## Drafts, autosave, conflicts, and publishing

- A changed draft autosaves after about 2.5 seconds of inactivity.
- **Save draft** forces an immediate save.
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

Desktop/tablet provides the most efficient complete workspace. On narrower screens, panels stack and controls wrap so the design can still be previewed, selected, adjusted, saved, published, exported, and printed.

For complex work—many layers, multi-selection, precise alignment, detailed text, or duplex setup—use a keyboard/mouse and a desktop/tablet. On a phone, prefer review, simple property changes, movement, save, and emergency export. Always reopen the final design at desktop width before publication.

## Security and data rules

- Documents store allowlisted object types: text, rectangle, ellipse, line, image, QR, and barcode.
- Documents are size/object-count bounded and canonicalized server-side.
- Arbitrary HTML, JavaScript, provider URLs, unknown bindings, unsupported Fabric objects, and unsafe colours/values are rejected.
- Assets require permission and are proxied from approved storage through the school origin.
- Local pinned Fabric.js, QR, JsBarcode, and jsPDF files prevent third-party runtime/CDN dependence.
- The QR library receives only the credential selected for output; no external QR image API sees it.
- Optimistic checksum conflicts prevent silent last-writer overwrite.
- Conversion, saves, uploads, and publishing are auditable/versioned.

Do not upload unlicensed fonts/artwork or private source documents that do not belong on an ID card.

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
