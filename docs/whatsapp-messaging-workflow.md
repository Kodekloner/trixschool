# WhatsApp messaging workflow

## Sending from the school portal

1. Open **System Settings > WhatsApp Settings** and select the school's provider.
2. For **Meta Cloud API**, enter the business sender's **Phone Number ID**, a valid **Access Token**, and a Graph API version supported by the account. The Phone Number ID is Meta's identifier, not the school's telephone number. For **360dialog (managed provider)**, enter the activated number's **Channel API Key** instead. Enable sending and save. For **Custom Webhook**, configure the delivery service's URL and optional bearer token. See the [managed coexistence setup guide](meta-whatsapp-account-check-2026-10-14.md#managed-production-setup-with-360dialog) for the documented production route and its activation status.
3. Confirm recipient contacts are recorded: **Guardians** use `students.guardian_phone`, **Students** use `students.mobileno`, and staff role groups use `staff.contact_no`. Nigerian local numbers such as `08012345678` are normalized using the configured country code; use `+countrycode...` for international numbers.
4. Open **Communicate > Send WhatsApp**. Any missing provider configuration is shown on the page and blocks the Send button.
5. For Meta or 360dialog, choose **Approved WhatsApp template** to start a conversation. Enter the exact approved template name and language from WhatsApp Manager or the connected provider account. For numbered text values in the body, enter one value per line in approved order. For example, two body values can be `{{display_name}}` on the first line and `{{school_name}}` on the second. Leave the values blank if the template has no values. Select the recipient groups and enter a title for the school log.
6. Use **Text reply within 24 hours** only after the recipient has messaged the school's business number within the last 24 hours. The school message templates copied from Notification Setting are editable text; selecting one does not create or select a Meta-approved template. Meta's [official quickstart](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/) explains the reply window, and its [Cloud API examples](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api) show approved template requests.

The approved template form supports templates with numbered text parameters in the body, including templates with no parameters. Templates requiring dynamic headers, media, button parameters, or named parameters need additional component support. Meta receives the approved template and the supplied values; the school's log title is not part of the WhatsApp message.

The **Login Credential Template Name** in WhatsApp Settings applies to the existing login credential workflow. Its values still follow **Template Parameter Order**. It is separate from the approved template selected for a composed school notice.

The WhatsApp button beside a contact opens a prefilled WhatsApp chat. The person using that chat presses Send in WhatsApp. Automatic portal sending uses the configured API or webhook provider.

## Interpreting the result

The send result reports **Accepted by WhatsApp provider**, **Failed**, and **Missing phone**. A Meta or 360dialog response must contain a message ID before it counts as accepted; a paused response counts as failure. HTTP errors and explicit webhook failure responses count as failures. An entirely rejected send does not create a successful message log.

Acceptance is the provider receiving the request. Delivery to a phone happens asynchronously; this compose workflow does not receive or store Meta delivery-status webhooks. Confirm delivery on the test phone and use WhatsApp Manager/provider status information to investigate later delivery failures. Meta's [message example](https://www.postman.com/meta/whatsapp-business-platform/request/o65u5m5/send-message-template-text) documents the template send endpoint.

Specific errors include invalid/expired access tokens, a closed reply window, unapproved template names or languages, incorrect body value counts, disallowed test recipients, and account payment/eligibility problems. The application reports safe explanations and numeric provider codes without logging raw responses that may contain credentials or phone numbers.

Bulk sends run synchronously, so a large group can exceed a hosting request time limit. Avoid blindly resending a whole group after an interrupted request, since some requests may already have been accepted. A persistent queue and delivery-status webhook processing are separate work from this correction.

## Verification

Run `php tests/whatsapp_compose_workflow_test.php`. It also runs the gateway tests. The checks exercise real CodeIgniter form validation and simulated provider responses, including guardian phone selection, personalized template parameters, text/webhook compatibility, missing contacts, partial failures, request permissions, and safe error reporting. No real provider is contacted.

For managed-provider support, also run `php tests/whatsapp_360dialog_test.php` and `php tests/whatsapp_provider_config_test.php`. These cover channel-key authentication, platform request/response handling and saving or switching provider credentials. Saved Meta tokens and 360dialog keys are omitted from settings page HTML; a blank credential field preserves the existing value only when keeping the same provider.

During this investigation, the local default database rejected its configured connection with MySQL error 1045. Live school settings and delivery could not be verified. The supplied database snapshots are historical and can differ from the running school database. Apply the code to the running installation and verify its settings there; no account credentials or database settings were changed by this fix.

The live demo at `https://demo.schoollift.com.ng/admin/whatsappsettings` redirects to the administrator sign-in page without an authenticated session. After deploying to the demo, an administrator must check the saved provider settings and test delivery. Other schools receive the shared code correction when their installations pull the merged branch. Their database settings, Meta approvals and guardian contacts remain school-specific. This correction adds no new database migration; an older installation still needs the existing `whatsapp_config` table.
