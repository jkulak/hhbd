<?php

/**
 * A user's password, hashed and checked (#41). New and changed passwords get password_hash():
 * bcrypt, with a work factor and a salt of their own. Before, every account had
 * md5(password . salt), with one salt for all, written in this public repository: a leaked
 * hhb_users would have fallen to a dictionary in minutes.
 *
 * An account from before still logs in with its MD5, checked with the old salt from the
 * environment (LEGACY_PASSWORD_SALT), and that login writes a password_hash() in its place. How
 * many are left: SELECT COUNT(*) FROM hhb_users WHERE usr_password REGEXP '^[0-9a-f]{32}$'.
 */
class Model_User_Password
{
    /** A hash for a new or changed password */
    public static function hash($password)
    {
        return password_hash((string) $password, PASSWORD_DEFAULT);
    }

    /**
     * Whether $password is the one $stored was made from: a password_hash(), or an MD5 from
     * before #41, which needs the old salt and never matches without it.
     */
    public static function verify($password, $stored, $legacySalt)
    {
        $password = (string) $password;
        $stored = (string) $stored;
        if (self::isLegacy($stored)) {
            return '' !== (string) $legacySalt && hash_equals($stored, md5($password . $legacySalt));
        }
        return password_verify($password, $stored);
    }

    /** Whether $stored is an MD5 from before #41 */
    public static function isLegacy($stored)
    {
        return 1 === preg_match('/^[0-9a-f]{32}$/', (string) $stored);
    }

    /** Whether a login that $stored let in should write a new hash: an MD5, or an old cost */
    public static function needsRehash($stored)
    {
        return self::isLegacy($stored) || password_needs_rehash((string) $stored, PASSWORD_DEFAULT);
    }

    /** The salt of the MD5s from before #41, from the environment; empty where none is set */
    public static function legacySalt()
    {
        return (string) getenv('LEGACY_PASSWORD_SALT');
    }
}
