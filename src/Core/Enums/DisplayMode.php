<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Enums;

/**
 * Widget display mode
 */
enum DisplayMode: string
{
    case Text = 'text';           // Data displayed directly in table
    case Accordion = 'accordion'; // Expandable rows
    case Modal = 'modal';         // Modal windows
}
