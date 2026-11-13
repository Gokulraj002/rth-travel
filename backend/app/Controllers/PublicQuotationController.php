<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\License;
use App\Core\Response;
use App\Core\Template;
use App\Repositories\QuotationRepository as Quotes;

/**
 * Public share page /q/{token} — no login. Read-only: nothing is written to the database on view.
 * Internal notes are never shown.
 */
final class PublicQuotationController extends Controller
{
    public function show(string $token): never
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token) || !License::has('quotations')) {
            Response::abort(404);
        }
        $q = Quotes::findByToken($token);
        if ($q === null) {
            Response::abort(404);
        }
        $qid = (int) $q['id'];
        unset($q['notes'], $q['created_by'], $q['enquiry_id']);

        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: private, no-store');

        $status = Quotes::effectiveStatus($q);
        $toolbar = '';
        $wa = setting('whatsapp_number') !== '' ? setting('whatsapp_number') : setting('phone_primary');
        if ($wa !== '' && in_array($status, ['draft', 'sent', 'accepted'], true)) {
            $text = 'Hi ' . (Template::companyVars()['company_name'] ?: 'there') . ", I'd like to confirm quotation " . $q['number'] . ' for ' . $q['title'] . ' (' . inr($q['total']) . ').';
            $toolbar = '<a class="btn btn-success" href="' . e(wa_link($wa, $text)) . '" target="_blank" rel="noopener">'
                . icon('whatsapp') . ' Confirm on WhatsApp</a>';
        }

        $this->view('public/quotation', [
            'title' => 'Quotation ' . $q['number'] . ' — ' . $q['title'],
            'public' => true,
            'toolbar' => $toolbar,
            'q' => $q,
            'status' => $status,
            'items' => Quotes::items($qid),
            'days' => Quotes::days($qid),
        ], 'layouts/print');
    }
}
