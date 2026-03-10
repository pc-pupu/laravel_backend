<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * CMS content type slugs (mirrors legacy Drupal cms_content module).
 * Used for validation and public listing behaviour (single vs list).
 */
final class CmsContentType
{
    public const FAQ = 'faq';
    public const ABOUT_US = 'about_us';
    public const CONTACT_US = 'contact_us';
    public const WHAT_IS_NEW = 'what_is_new';
    public const NOTICE = 'notice';
    public const USER_MANUAL = 'user_manual';

    /** @var list<string> */
    public const ALL = [
        self::FAQ,
        self::ABOUT_US,
        self::CONTACT_US,
        self::WHAT_IS_NEW,
        self::NOTICE,
        self::USER_MANUAL,
    ];

    /** Content types that show multiple items on the public page (array response). */
    public const LIST_TYPES = [
        self::FAQ,
        self::NOTICE,
        self::USER_MANUAL,
    ];

    public static function toSelectOptions(): array
    {
        return [
            self::FAQ          => 'FAQ',
            self::ABOUT_US     => 'About Us',
            self::CONTACT_US   => 'Contact Us',
            self::WHAT_IS_NEW  => "What's New",
            self::NOTICE       => 'Notice',
            self::USER_MANUAL  => 'User Manual',
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array(strtolower(str_replace('-', '_', trim($type))), self::ALL, true);
    }

    public static function normalize(string $type): string
    {
        $type = strtolower(str_replace('-', '_', trim($type)));
        return self::isValid($type) ? $type : $type;
    }
}
