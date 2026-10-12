<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/** Resolve an email only from explicitly guardian/parent-labelled contacts. */
class Biometric_guardian_contact
{
    public static function email($guardianEmail, array $customFields = array())
    {
        $guardianEmail = trim((string) $guardianEmail);
        if ($guardianEmail !== '') {
            return filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)
                ? array('recipient' => $guardianEmail, 'error' => null)
                : self::failure('The recorded guardian email address is invalid.');
        }

        $addresses = array();
        foreach ($customFields as $field) {
            $name = strtolower(preg_replace('/[^a-z]/i', '', (string) $field['name']));
            if (!in_array($name, array('parentemail', 'parentsemail', 'guardianemail',
                'guardiansemail', 'parentguardianemail', 'parentorguardianemail'), true)) {
                continue;
            }
            $value = trim((string) $field['field_value']);
            if ($value === '') {
                continue;
            }
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return self::failure('A parent/guardian custom email field contains an invalid address.');
            }
            $addresses[$value] = $value;
        }
        if (count($addresses) > 1) {
            return self::failure('Multiple parent/guardian custom email addresses are recorded. Set the standard Guardian Email to select the recipient.');
        }
        return $addresses
            ? array('recipient' => reset($addresses), 'error' => null)
            : self::failure('No guardian email address is recorded in Guardian Email or a recognised parent/guardian custom email field.');
    }

    private static function failure($message)
    {
        return array('recipient' => '', 'error' => $message);
    }
}
