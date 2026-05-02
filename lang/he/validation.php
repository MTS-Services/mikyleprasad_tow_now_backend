<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'יש לאשר את השדה :attribute.',
    'accepted_if' => 'יש לאשר את השדה :attribute כאשר :other הוא :value.',
    'active_url' => 'השדה :attribute חייב להיות כתובת URL תקינה.',
    'after' => 'השדה :attribute חייב להיות תאריך לאחר :date.',
    'after_or_equal' => 'השדה :attribute חייב להיות תאריך לאחר או שווה ל-:date.',
    'alpha' => 'השדה :attribute חייב להכיל אותיות בלבד.',
    'alpha_dash' => 'השדה :attribute חייב להכיל אותיות, מספרים, מקפים וקווים תחתונים בלבד.',
    'alpha_num' => 'השדה :attribute חייב להכיל אותיות ומספרים בלבד.',
    'any_of' => 'השדה :attribute אינו תקין.',
    'array' => 'השדה :attribute חייב להיות מערך.',
    'ascii' => 'השדה :attribute חייב להכיל תווים וסמלים מסוג ASCII בלבד.',
    'before' => 'השדה :attribute חייב להיות תאריך לפני :date.',
    'before_or_equal' => 'השדה :attribute חייב להיות תאריך לפני או שווה ל-:date.',
    'between' => [
        'array' => 'השדה :attribute חייב להכיל בין :min ל-:max פריטים.',
        'file' => 'השדה :attribute חייב להיות בין :min ל-:max קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות בין :min ל-:max.',
        'string' => 'השדה :attribute חייב להיות בין :min ל-:max תווים.',
    ],
    'boolean' => 'השדה :attribute חייב להיות אמת או שקר.',
    'can' => 'השדה :attribute מכיל ערך לא מורשה.',
    'confirmed' => 'אישור השדה :attribute אינו תואם.',
    'contains' => 'השדה :attribute חסר ערך נדרש.',
    'current_password' => 'הסיסמה שגויה.',
    'date' => 'השדה :attribute חייב להיות תאריך תקין.',
    'date_equals' => 'השדה :attribute חייב להיות תאריך השווה ל-:date.',
    'date_format' => 'השדה :attribute חייב להתאים לפורמט :format.',
    'decimal' => 'השדה :attribute חייב לכלול :decimal ספרות אחרי הנקודה.',
    'declined' => 'השדה :attribute חייב להידחות.',
    'declined_if' => 'השדה :attribute חייב להידחות כאשר :other הוא :value.',
    'different' => 'השדה :attribute וה-:other חייבים להיות שונים.',
    'digits' => 'השדה :attribute חייב להיות :digits ספרות.',
    'digits_between' => 'השדה :attribute חייב להיות בין :min ל-:max ספרות.',
    'dimensions' => 'לשדה :attribute יש ממדי תמונה לא תקינים.',
    'distinct' => 'לשדה :attribute יש ערך כפול.',
    'doesnt_contain' => 'השדה :attribute לא יכול להכיל את הערכים הבאים: :values.',
    'doesnt_end_with' => 'השדה :attribute לא יכול להסתיים באחד מהערכים הבאים: :values.',
    'doesnt_start_with' => 'השדה :attribute לא יכול להתחיל באחד מהערכים הבאים: :values.',
    'email' => 'השדה :attribute חייב להיות כתובת דוא\"ל תקינה.',
    'encoding' => 'השדה :attribute חייב להיות מקודד ב-:encoding.',
    'ends_with' => 'השדה :attribute חייב להסתיים באחד מהערכים הבאים: :values.',
    'enum' => 'הערך שנבחר עבור :attribute אינו תקין.',
    'exists' => 'הערך שנבחר עבור :attribute אינו תקין.',
    'extensions' => 'השדה :attribute חייב להיות עם אחת מהסיומות הבאות: :values.',
    'file' => 'השדה :attribute חייב להיות קובץ.',
    'filled' => 'השדה :attribute חייב להכיל ערך.',
    'gt' => [
        'array' => 'השדה :attribute חייב להכיל יותר מ-:value פריטים.',
        'file' => 'השדה :attribute חייב להיות גדול מ-:value קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות גדול מ-:value.',
        'string' => 'השדה :attribute חייב להיות גדול מ-:value תווים.',
    ],
    'gte' => [
        'array' => 'השדה :attribute חייב להכיל :value פריטים או יותר.',
        'file' => 'השדה :attribute חייב להיות גדול או שווה ל-:value קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות גדול או שווה ל-:value.',
        'string' => 'השדה :attribute חייב להיות גדול או שווה ל-:value תווים.',
    ],
    'hex_color' => 'השדה :attribute חייב להיות צבע הקסדצימלי תקין.',
    'image' => 'השדה :attribute חייב להיות תמונה.',
    'in' => 'הערך שנבחר עבור :attribute אינו תקין.',
    'in_array' => 'השדה :attribute חייב להיות קיים בתוך :other.',
    'in_array_keys' => 'השדה :attribute חייב להכיל לפחות אחד מהמפתחות הבאים: :values.',
    'integer' => 'השדה :attribute חייב להיות מספר שלם.',
    'ip' => 'השדה :attribute חייב להיות כתובת IP תקינה.',
    'ipv4' => 'השדה :attribute חייב להיות כתובת IPv4 תקינה.',
    'ipv6' => 'השדה :attribute חייב להיות כתובת IPv6 תקינה.',
    'json' => 'השדה :attribute חייב להיות מחרוזת JSON תקינה.',
    'list' => 'השדה :attribute חייב להיות רשימה.',
    'lowercase' => 'השדה :attribute חייב להיות באותיות קטנות.',
    'lt' => [
        'array' => 'השדה :attribute חייב להכיל פחות מ-:value פריטים.',
        'file' => 'השדה :attribute חייב להיות קטן מ-:value קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות קטן מ-:value.',
        'string' => 'השדה :attribute חייב להיות קטן מ-:value תווים.',
    ],
    'lte' => [
        'array' => 'השדה :attribute לא יכול להכיל יותר מ-:value פריטים.',
        'file' => 'השדה :attribute חייב להיות קטן או שווה ל-:value קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות קטן או שווה ל-:value.',
        'string' => 'השדה :attribute חייב להיות קטן או שווה ל-:value תווים.',
    ],
    'mac_address' => 'השדה :attribute חייב להיות כתובת MAC תקינה.',
    'max' => [
        'array' => 'השדה :attribute לא יכול להכיל יותר מ-:max פריטים.',
        'file' => 'השדה :attribute לא יכול להיות גדול מ-:max קילובייט.',
        'numeric' => 'השדה :attribute לא יכול להיות גדול מ-:max.',
        'string' => 'השדה :attribute לא יכול להיות גדול מ-:max תווים.',
    ],
    'max_digits' => 'השדה :attribute לא יכול להכיל יותר מ-:max ספרות.',
    'mimes' => 'השדה :attribute חייב להיות קובץ מסוג: :values.',
    'mimetypes' => 'השדה :attribute חייב להיות קובץ מסוג: :values.',
    'min' => [
        'array' => 'השדה :attribute חייב להכיל לפחות :min פריטים.',
        'file' => 'השדה :attribute חייב להיות לפחות :min קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות לפחות :min.',
        'string' => 'השדה :attribute חייב להיות לפחות :min תווים.',
    ],
    'min_digits' => 'השדה :attribute חייב להכיל לפחות :min ספרות.',
    'missing' => 'השדה :attribute חייב להיות חסר.',
    'missing_if' => 'השדה :attribute חייב להיות חסר כאשר :other הוא :value.',
    'missing_unless' => 'השדה :attribute חייב להיות חסר אלא אם :other הוא :value.',
    'missing_with' => 'השדה :attribute חייב להיות חסר כאשר :values קיימים.',
    'missing_with_all' => 'השדה :attribute חייב להיות חסר כאשר :values קיימים.',
    'multiple_of' => 'השדה :attribute חייב להיות כפולה של :value.',
    'not_in' => 'הערך שנבחר עבור :attribute אינו תקין.',
    'not_regex' => 'פורמט השדה :attribute אינו תקין.',
    'numeric' => 'השדה :attribute חייב להיות מספר.',
    'password' => [
        'letters' => 'השדה :attribute חייב להכיל לפחות אות אחת.',
        'mixed' => 'השדה :attribute חייב להכיל לפחות אות גדולה ואות קטנה.',
        'numbers' => 'השדה :attribute חייב להכיל לפחות מספר אחד.',
        'symbols' => 'השדה :attribute חייב להכיל לפחות סימן אחד.',
        'uncompromised' => 'ה-:attribute הופיע בדליפת נתונים. אנא בחר/י :attribute אחר.',
    ],
    'present' => 'השדה :attribute חייב להיות קיים.',
    'present_if' => 'השדה :attribute חייב להיות קיים כאשר :other הוא :value.',
    'present_unless' => 'השדה :attribute חייב להיות קיים אלא אם :other הוא :value.',
    'present_with' => 'השדה :attribute חייב להיות קיים כאשר :values קיימים.',
    'present_with_all' => 'השדה :attribute חייב להיות קיים כאשר :values קיימים.',
    'prohibited' => 'השדה :attribute אסור.',
    'prohibited_if' => 'השדה :attribute אסור כאשר :other הוא :value.',
    'prohibited_if_accepted' => 'השדה :attribute אסור כאשר :other התקבל.',
    'prohibited_if_declined' => 'השדה :attribute אסור כאשר :other נדחה.',
    'prohibited_unless' => 'השדה :attribute אסור אלא אם :other נמצא בתוך :values.',
    'prohibits' => 'השדה :attribute אוסר על :other להיות קיים.',
    'regex' => 'פורמט השדה :attribute אינו תקין.',
    'required' => 'השדה :attribute הוא שדה חובה.',
    'required_array_keys' => 'השדה :attribute חייב להכיל ערכים עבור: :values.',
    'required_if' => 'השדה :attribute הוא חובה כאשר :other הוא :value.',
    'required_if_accepted' => 'השדה :attribute הוא חובה כאשר :other התקבל.',
    'required_if_declined' => 'השדה :attribute הוא חובה כאשר :other נדחה.',
    'required_unless' => 'השדה :attribute הוא חובה אלא אם :other נמצא בתוך :values.',
    'required_with' => 'השדה :attribute הוא חובה כאשר :values קיימים.',
    'required_with_all' => 'השדה :attribute הוא חובה כאשר :values קיימים.',
    'required_without' => 'השדה :attribute הוא חובה כאשר :values אינם קיימים.',
    'required_without_all' => 'השדה :attribute הוא חובה כאשר אף אחד מהערכים :values אינם קיימים.',
    'same' => 'השדה :attribute חייב להתאים לשדה :other.',
    'size' => [
        'array' => 'השדה :attribute חייב להכיל :size פריטים.',
        'file' => 'השדה :attribute חייב להיות :size קילובייט.',
        'numeric' => 'השדה :attribute חייב להיות :size.',
        'string' => 'השדה :attribute חייב להיות :size תווים.',
    ],
    'starts_with' => 'השדה :attribute חייב להתחיל באחד מהערכים הבאים: :values.',
    'string' => 'השדה :attribute חייב להיות מחרוזת.',
    'timezone' => 'השדה :attribute חייב להיות אזור זמן תקין.',
    'unique' => 'ה-:attribute כבר תפוס.',
    'uploaded' => 'העלאת השדה :attribute נכשלה.',
    'uppercase' => 'השדה :attribute חייב להיות באותיות גדולות.',
    'url' => 'השדה :attribute חייב להיות כתובת URL תקינה.',
    'ulid' => 'השדה :attribute חייב להיות ULID תקין.',
    'uuid' => 'השדה :attribute חייב להיות UUID תקין.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];

