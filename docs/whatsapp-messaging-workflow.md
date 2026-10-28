# WhatsApp messaging workflow

WhatsApp now uses Twilio. Configure it under **System Settings > WhatsApp Settings** and follow the [Sandbox and production walkthrough](meta-whatsapp-account-check-2026-10-14.md).

1. Save separate Sandbox/Production Account SIDs, Auth Tokens, senders and test recipients. New or legacy installations start disabled in Sandbox. The first save creates **whatsapp_twilio_config**; if CREATE is unavailable, apply [the supplied SQL](whatsapp-twilio-config.sql).
2. Use **Check connection**, **Send one test message** and **Check last test status** in Settings before production. Tests use the selected saved environment and its saved recipient, even with school sending off. A connection check authenticates the account without sending. Acceptance/queued status is not delivery; delivered/read confirms Twilio's reported delivery.
3. Sandbox blocks group/automatic school sending. After verifying production, set **School environment: Production** and **Enable school sending: Yes**.
4. In **Communicate > Send WhatsApp**, choose **Approved WhatsApp template**. Enter a **Twilio Content SID** (HX plus 32 hex characters), then one numbered text variable per line. Values can use **{{display_name}}**, **{{student_name}}** or **{{school_name}}** for personalization. The Content SID selects wording and language.
5. Choose recipient groups and a log title. Guardians use **students.guardian_phone**, Students use **students.mobileno** and staff use **staff.contact_no**. Start with a role containing one test account.
6. **Text reply within 24 hours** requires a recent inbound message. Notification Setting text templates do not become approved Content templates automatically.

Results count **Accepted by WhatsApp provider**, **Failed** and **Missing phone**. Acceptance requires a valid Twilio Message SID and non-failure status. Total rejection does not create a successful message log. Partial failures include safe errors and numeric codes.

Template text variables use sequential numbers starting at 1. Advanced media components are outside this form. Login credentials have a separate production Content SID and configurable parameter order; biometric text notifications remain subject to the reply window.

Test delivery status is fetched by the settings editor. General delivery callbacks, inbound processing and persistent bulk queues are not implemented. Large synchronous groups can exceed hosting time limits; inspect Twilio logs before resending an interrupted request.

The contact WhatsApp button still opens a prefilled chat; its operator presses Send. It does not use the automatic gateway.

Twilio messaging charges and WhatsApp production requirements apply: [pricing](https://www.twilio.com/en-us/whatsapp/pricing), [Sandbox](https://www.twilio.com/docs/whatsapp/sandbox), [Messages API](https://www.twilio.com/docs/messaging/api/message-resource).

Run **php tests/whatsapp_admin_workflow_test.php** and **php tests/whatsapp_compose_workflow_test.php**. They simulate Twilio and send no real messages.
