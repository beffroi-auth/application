<?php

declare(strict_types=1);

namespace App\Command;

use function mb_scrub;
use function preg_replace;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * What a command hands the console of a string it did not write: a name a person chose, a member a client registered
 * (decision 0009), what an administrator or a directory wrote, what a remote source answered. A terminal runs what it
 * is sent: an escape sequence sets the title, writes the clipboard (OSC 52) or moves the cursor over what was written,
 * a bidirectional control makes a name read as another one (CVE-2021-42574), and the formatter obeys its markup
 * (`<href=…>`, `<fg=…>`). A string is made well-formed UTF-8 first, so that removing a byte cannot splice two others
 * into a control, then loses every C0 control but the tab and the line feed, DEL, every C1 control and every
 * bidirectional control; what followed an introducer stays, as visible text.
 */
final readonly class PrintedText
{
    /**
     * C0 but TAB and LF, DEL, C1 (U+0080 to U+009F), and the bidirectional embeddings, overrides and isolates with the
     * marks that steer them (U+061C, U+200E, U+200F, U+202A to U+202E, U+2066 to U+2069).
     */
    private const string CONTROLS = '/[\x00-\x08\x0B-\x1F\x7F\x{0080}-\x{009F}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /**
     * For a line, a title or a cell of a table, which the formatter reads as it is: one line, its markup escaped.
     */
    public static function of(string $untrusted): string
    {
        return OutputFormatter::escape(preg_replace('/[\t\n]+/', ' ', self::inBlock($untrusted)) ?? '');
    }

    /**
     * For a block of SymfonyStyle, `error()`, `warning()`, `note()` or `success()`, which escapes the markup of its
     * message itself: escaped twice, a "<" would be shown as "\<".
     */
    public static function inBlock(string $untrusted): string
    {
        return preg_replace(self::CONTROLS, '', mb_scrub($untrusted, 'UTF-8')) ?? '';
    }
}
