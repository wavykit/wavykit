<?php

declare(strict_types=1);

namespace WavyKit\Navigation;

/** What a menu item represents; serializes to its string value. */
enum MenuItemKind: string
{
    /** A registered page, linked when it has a route. */
    case Page = 'page';

    /** A presentation-only group with no page ID or URL. */
    case Group = 'group';
}
