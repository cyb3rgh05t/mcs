<?php

/**
 * Mobile Car Service - Input Validator
 * Sichere Validierung aller Eingabedaten
 */

class Validator
{
    private $errors = [];
    private $customMessages = [];

    /**
     * Daten validieren
     */
    public function validate($data, $rules, $customMessages = [])
    {
        $this->errors = [];
        $this->customMessages = $customMessages;

        foreach ($rules as $field => $fieldRules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $this->validateField($field, $value, $fieldRules);
        }

        return $this->errors;
    }

    /**
     * Einzelnes Feld validieren
     */
    private function validateField($field, $value, $rules)
    {
        foreach ($rules as $rule) {
            // Rule parsen
            $ruleName = $rule;
            $ruleValue = null;

            if (strpos($rule, ':') !== false) {
                list($ruleName, $ruleValue) = explode(':', $rule, 2);
            }

            // Validierung durchführen
            $isValid = $this->applyRule($field, $value, $ruleName, $ruleValue);

            if (!$isValid) {
                break; // Bei erstem Fehler stoppen
            }
        }
    }

    /**
     * Validierungsregel anwenden
     */
    private function applyRule($field, $value, $ruleName, $ruleValue)
    {
        switch ($ruleName) {
            case 'required':
                return $this->validateRequired($field, $value);

            case 'email':
                return $this->validateEmail($field, $value);

            case 'min':
                return $this->validateMin($field, $value, $ruleValue);

            case 'max':
                return $this->validateMax($field, $value, $ruleValue);

            case 'numeric':
                return $this->validateNumeric($field, $value);

            case 'integer':
                return $this->validateInteger($field, $value);

            case 'date':
                return $this->validateDate($field, $value);

            case 'time':
                return $this->validateTime($field, $value);

            case 'regex':
                return $this->validateRegex($field, $value, $ruleValue);

            case 'in':
                return $this->validateIn($field, $value, $ruleValue);

            case 'array':
                return $this->validateArray($field, $value);

            case 'url':
                return $this->validateUrl($field, $value);

            case 'phone':
                return $this->validatePhone($field, $value);

            case 'zip':
                return $this->validateZip($field, $value);

            case 'alpha':
                return $this->validateAlpha($field, $value);

            case 'alphanumeric':
                return $this->validateAlphanumeric($field, $value);

            case 'confirmed':
                return $this->validateConfirmed($field, $value, $ruleValue);

            default:
                return true; // Unbekannte Regel ignorieren
        }
    }

    /**
     * Required-Validierung
     */
    private function validateRequired($field, $value)
    {
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required', 'Das Feld :field ist erforderlich');
            return false;
        }
        return true;
    }

    /**
     * E-Mail-Validierung
     */
    private function validateEmail($field, $value)
    {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', 'Das Feld :field muss eine gültige E-Mail-Adresse sein');
            return false;
        }
        return true;
    }

    /**
     * Minimum-Länge/Wert-Validierung
     */
    private function validateMin($field, $value, $min)
    {
        if (is_null($value)) {
            return true; // Null-Werte überspringen (required-Rule verwenden)
        }

        if (is_numeric($value)) {
            if ($value < $min) {
                $this->addError($field, 'min', "Das Feld :field muss mindestens $min sein");
                return false;
            }
        } else {
            $length = is_string($value) ? mb_strlen($value) : count($value);
            if ($length < $min) {
                $this->addError($field, 'min', "Das Feld :field muss mindestens $min Zeichen haben");
                return false;
            }
        }
        return true;
    }

    /**
     * Maximum-Länge/Wert-Validierung
     */
    private function validateMax($field, $value, $max)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_numeric($value)) {
            if ($value > $max) {
                $this->addError($field, 'max', "Das Feld :field darf höchstens $max sein");
                return false;
            }
        } else {
            $length = is_string($value) ? mb_strlen($value) : count($value);
            if ($length > $max) {
                $this->addError($field, 'max', "Das Feld :field darf höchstens $max Zeichen haben");
                return false;
            }
        }
        return true;
    }

    /**
     * Numeric-Validierung
     */
    private function validateNumeric($field, $value)
    {
        if (!is_null($value) && !is_numeric($value)) {
            $this->addError($field, 'numeric', 'Das Feld :field muss eine Zahl sein');
            return false;
        }
        return true;
    }

    /**
     * Integer-Validierung
     */
    private function validateInteger($field, $value)
    {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', 'Das Feld :field muss eine ganze Zahl sein');
            return false;
        }
        return true;
    }

    /**
     * Datum-Validierung
     */
    private function validateDate($field, $value)
    {
        if (!is_null($value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, 'date', 'Das Feld :field muss ein gültiges Datum im Format YYYY-MM-DD sein');
                return false;
            }
        }
        return true;
    }

    /**
     * Zeit-Validierung
     */
    private function validateTime($field, $value)
    {
        if (!is_null($value)) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                $this->addError($field, 'time', 'Das Feld :field muss eine gültige Zeit im Format HH:MM sein');
                return false;
            }
        }
        return true;
    }

    /**
     * Regex-Validierung
     */
    private function validateRegex($field, $value, $pattern)
    {
        if (!is_null($value) && !preg_match($pattern, $value)) {
            $this->addError($field, 'regex', 'Das Feld :field hat ein ungültiges Format');
            return false;
        }
        return true;
    }

    /**
     * In-Array-Validierung
     */
    private function validateIn($field, $value, $allowedValues)
    {
        if (!is_null($value)) {
            $allowed = is_string($allowedValues) ? explode(',', $allowedValues) : $allowedValues;
            if (!in_array($value, $allowed)) {
                $this->addError($field, 'in', 'Das Feld :field muss einen der folgenden Werte haben: ' . implode(', ', $allowed));
                return false;
            }
        }
        return true;
    }

    /**
     * Array-Validierung
     */
    private function validateArray($field, $value)
    {
        if (!is_null($value) && !is_array($value)) {
            $this->addError($field, 'array', 'Das Feld :field muss ein Array sein');
            return false;
        }
        return true;
    }

    /**
     * URL-Validierung
     */
    private function validateUrl($field, $value)
    {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', 'Das Feld :field muss eine gültige URL sein');
            return false;
        }
        return true;
    }

    /**
     * Telefon-Validierung
     */
    private function validatePhone($field, $value)
    {
        if (!is_null($value)) {
            // Deutsche Telefonnummern + internationale Formate
            $pattern = '/^[\+]?[0-9\s\-\(\)]{10,}$/';
            if (!preg_match($pattern, $value)) {
                $this->addError($field, 'phone', 'Das Feld :field muss eine gültige Telefonnummer sein');
                return false;
            }
        }
        return true;
    }

    /**
     * PLZ-Validierung (Deutschland)
     */
    private function validateZip($field, $value)
    {
        if (!is_null($value)) {
            if (!preg_match('/^[0-9]{5}$/', $value)) {
                $this->addError($field, 'zip', 'Das Feld :field muss eine gültige deutsche PLZ (5 Ziffern) sein');
                return false;
            }
        }
        return true;
    }

    /**
     * Nur Buchstaben-Validierung
     */
    private function validateAlpha($field, $value)
    {
        if (!is_null($value)) {
            if (!preg_match('/^[a-zA-ZäöüÄÖÜß\s]+$/', $value)) {
                $this->addError($field, 'alpha', 'Das Feld :field darf nur Buchstaben enthalten');
                return false;
            }
        }
        return true;
    }

    /**
     * Buchstaben und Zahlen-Validierung
     */
    private function validateAlphanumeric($field, $value)
    {
        if (!is_null($value)) {
            if (!preg_match('/^[a-zA-Z0-9äöüÄÖÜß\s]+$/', $value)) {
                $this->addError($field, 'alphanumeric', 'Das Feld :field darf nur Buchstaben und Zahlen enthalten');
                return false;
            }
        }
        return true;
    }

    /**
     * Bestätigung-Validierung (z.B. password_confirmation)
     */
    private function validateConfirmed($field, $value, $data)
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = isset($data[$confirmationField]) ? $data[$confirmationField] : null;

        if ($value !== $confirmationValue) {
            $this->addError($field, 'confirmed', 'Das Feld :field stimmt nicht mit der Bestätigung überein');
            return false;
        }
        return true;
    }

    /**
     * Fehler hinzufügen
     */
    private function addError($field, $rule, $message)
    {
        // Custom Message verwenden falls vorhanden
        $customKey = "$field.$rule";
        if (isset($this->customMessages[$customKey])) {
            $message = $this->customMessages[$customKey];
        }

        // Platzhalter ersetzen
        $message = str_replace(':field', $field, $message);

        $this->errors[$field] = $message;
    }

    /**
     * Alle Fehler abrufen
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Prüfen ob Validierung erfolgreich war
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Prüfen ob Validierung fehlgeschlagen ist
     */
    public function fails()
    {
        return !$this->passes();
    }

    /**
     * Ersten Fehler für ein Feld abrufen
     */
    public function first($field)
    {
        return isset($this->errors[$field]) ? $this->errors[$field] : null;
    }

    /**
     * Eingabedaten bereinigen
     */
    public static function sanitize($data, $rules = [])
    {
        if (!is_array($data)) {
            return $data;
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Grundlegende Bereinigung
                $value = trim($value);
                $value = stripslashes($value);

                // Spezielle Bereinigung basierend auf Regeln
                if (isset($rules[$key])) {
                    $fieldRules = $rules[$key];

                    if (in_array('email', $fieldRules)) {
                        $value = strtolower($value);
                        $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                    } elseif (in_array('numeric', $fieldRules)) {
                        $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    } elseif (in_array('integer', $fieldRules)) {
                        $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                    } elseif (in_array('url', $fieldRules)) {
                        $value = filter_var($value, FILTER_SANITIZE_URL);
                    } else {
                        // Standard-String-Bereinigung
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }

                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value, isset($rules[$key]) ? $rules[$key] : []);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Statische Validierungsmethoden für häufige Anwendungsfälle
     */

    /**
     * E-Mail validieren
     */
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Deutsche PLZ validieren
     */
    public static function isValidZip($zip)
    {
        return preg_match('/^[0-9]{5}$/', $zip);
    }

    /**
     * Telefonnummer validieren
     */
    public static function isValidPhone($phone)
    {
        return preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $phone);
    }

    /**
     * Datum validieren
     */
    public static function isValidDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Zeit validieren
     */
    public static function isValidTime($time)
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    /**
     * URL validieren
     */
    public static function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Starkes Passwort validieren
     */
    public static function isStrongPassword($password)
    {
        // Mindestens 8 Zeichen, Groß-/Kleinbuchstaben, Zahl und Sonderzeichen
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }

    /**
     * JSON validieren
     */
    public static function isValidJson($json)
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Credit Card Number validieren (Luhn-Algorithmus)
     */
    public static function isValidCreditCard($number)
    {
        $number = preg_replace('/\D/', '', $number);
        $length = strlen($number);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = (int)$number[$i];

            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }

            $sum += $digit;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }

    /**
     * IBAN validieren
     */
    public static function isValidIban($iban)
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$/', $iban)) {
            return false;
        }

        // IBAN Prüfsumme berechnen
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';

        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (is_numeric($char)) {
                $numeric .= $char;
            } else {
                $numeric .= (ord($char) - ord('A') + 10);
            }
        }

        return bcmod($numeric, '97') === '1';
    }

    /**
     * Deutsche Steuernummer validieren
     */
    public static function isValidTaxNumber($taxNumber)
    {
        // Format: XX/XXX/XXXXX oder XXXXXXXXXXX
        $pattern1 = '/^\d{2}\/\d{3}\/\d{5}$/';
        $pattern2 = '/^\d{11}$/';

        return preg_match($pattern1, $taxNumber) || preg_match($pattern2, $taxNumber);
    }

    /**
     * Farb-Hex-Code validieren
     */
    public static function isValidHexColor($color)
    {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
    }

    /**
     * IP-Adresse validieren
     */
    public static function isValidIp($ip, $version = null)
    {
        $flags = 0;

        if ($version === 4) {
            $flags = FILTER_FLAG_IPV4;
        } elseif ($version === 6) {
            $flags = FILTER_FLAG_IPV6;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * MAC-Adresse validieren
     */
    public static function isValidMac($mac)
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac);
    }

    /**
     * UUID validieren
     */
    public static function isValidUuid($uuid)
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }
}

/**
 * Helper-Funktion für einfache Validierung
 */
function validate($data, $rules, $customMessages = [])
{
    $validator = new Validator();
    $errors = $validator->validate($data, $rules, $customMessages);

    if (!empty($errors)) {
        throw new ValidationException($errors);
    }

    return true;
}

/**
 * Helper-Funktion für Datenbereinigung
 */
function sanitize($data, $rules = [])
{
    return Validator::sanitize($data, $rules);
}
