<?php
/**
 * UserMetaKeyType — User meta keys for social profiles and Yoast SEO.
 *
 * @package RiseupAsia\Enums
 * @since   2.13.0
 */

namespace RiseupAsia\Enums;

if (!defined('ABSPATH')) {
    exit;
}

enum UserMetaKeyType: string
{
    // ── Social Profiles ─────────────────────────────────────────────
    case Facebook   = 'facebook';
    case Instagram  = 'instagram';
    case LinkedIn   = 'linkedin';
    case MySpace    = 'myspace';
    case Pinterest  = 'pinterest';
    case SoundCloud = 'soundcloud';
    case Tumblr     = 'tumblr';
    case Wikipedia  = 'wikipedia';
    case X          = 'twitter';
    case YouTube    = 'youtube';
    case Mastodon   = 'mastodon';

    // ── Yoast SEO Schema ────────────────────────────────────────────
    case YoastHonorificPrefix      = 'wpseo_title_prefix';
    case YoastHonorificSuffix      = 'wpseo_title_suffix';
    case YoastBirthDate            = 'wpseo_birth_date';
    case YoastGender               = 'wpseo_gender';
    case YoastAwards               = 'wpseo_awards';
    case YoastExpertiseIn          = 'wpseo_expertise';
    case YoastLanguagesSpoken      = 'wpseo_languages';
    case YoastJobTitle             = 'wpseo_job_title';
    case YoastEmployerName         = 'wpseo_employer';
    case YoastAuthorTitle          = 'wpseo_title';
    case YoastAuthorMetaDescription = 'wpseo_metadesc';
    case YoastPronouns             = 'wpseo_pronouns';

    public function isEqual(self $other): bool { return $this === $other; }
    public function isOtherThan(self $other): bool { return $this !== $other; }
    public function isAnyOf(self ...$others): bool { return in_array($this, $others, true); }

    public function isSocial(): bool
    {
        return in_array($this, self::socialCases(), true);
    }

    public function isYoast(): bool
    {
        return str_starts_with($this->value, 'wpseo_');
    }

    /** @return self[] */
    public static function socialCases(): array
    {
        return array(
            self::Facebook,
            self::Instagram,
            self::LinkedIn,
            self::MySpace,
            self::Pinterest,
            self::SoundCloud,
            self::Tumblr,
            self::Wikipedia,
            self::X,
            self::YouTube,
            self::Mastodon,
        );
    }

    /** @return self[] */
    public static function yoastCases(): array
    {
        return array(
            self::YoastHonorificPrefix,
            self::YoastHonorificSuffix,
            self::YoastBirthDate,
            self::YoastGender,
            self::YoastAwards,
            self::YoastExpertiseIn,
            self::YoastLanguagesSpoken,
            self::YoastJobTitle,
            self::YoastEmployerName,
            self::YoastAuthorTitle,
            self::YoastAuthorMetaDescription,
            self::YoastPronouns,
        );
    }

    /**
     * JSON key name used in API responses.
     */
    public function jsonKey(): string
    {
        return match($this) {
            self::Facebook   => 'Facebook',
            self::Instagram  => 'Instagram',
            self::LinkedIn   => 'LinkedIn',
            self::MySpace    => 'MySpace',
            self::Pinterest  => 'Pinterest',
            self::SoundCloud => 'SoundCloud',
            self::Tumblr     => 'Tumblr',
            self::Wikipedia  => 'Wikipedia',
            self::X          => 'X',
            self::YouTube    => 'YouTube',
            self::Mastodon   => 'Mastodon',

            self::YoastHonorificPrefix      => 'HonorificPrefix',
            self::YoastHonorificSuffix      => 'HonorificSuffix',
            self::YoastBirthDate            => 'BirthDate',
            self::YoastGender               => 'Gender',
            self::YoastAwards               => 'Awards',
            self::YoastExpertiseIn          => 'ExpertiseIn',
            self::YoastLanguagesSpoken      => 'LanguagesSpoken',
            self::YoastJobTitle             => 'JobTitle',
            self::YoastEmployerName         => 'EmployerName',
            self::YoastAuthorTitle          => 'AuthorTitle',
            self::YoastAuthorMetaDescription => 'AuthorMetaDescription',
            self::YoastPronouns             => 'Pronouns',
        };
    }
}
