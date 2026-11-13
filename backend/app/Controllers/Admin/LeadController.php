<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\License;
use App\Core\Response;
use App\Repositories\EnquiryRepository as Enq;
use App\Repositories\LeadActivityRepository;

/** Lead pipeline (feature "crm"): kanban board + list view, stage moves. */
final class LeadController extends Controller
{
    public function index(): never
    {
        $filters = Enq::leadFilters(
            $this->request->query('q', ''),
            $this->request->query('assigned', ''),
            $this->request->query('priority', ''),
            $this->request->query('source', ''),
            $this->request->query('stage', ''),
        );
        $view = $this->request->query('view', '') === 'list' ? 'list' : 'board';
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $actions = '<a href="' . e(url('/enquiries/create')) . '" class="btn btn-primary">' . icon('plus') . ' New enquiry</a>';

        $this->view('admin/leads/index', [
            'title' => 'Lead pipeline',
            'pretitle' => 'Sales',
            'filters' => $filters,
            'viewMode' => $view,
            'board' => $view === 'board' ? Enq::board($filters) : null,
            'result' => $view === 'list' ? Enq::leadPaginate($filters, $page) : null,
            'assignees' => Enq::assignees(),
            'pageActions' => $actions,
        ]);
    }

    /**
     * POST /leads/{id}/stage — JSON {stage, lost_reason?} with X-CSRF-Token (kanban drag & drop),
     * or a normal form post (no-JS fallback).
     */
    public function stage(string $id): never
    {
        $json = $this->request->isJson();
        $lead = Enq::find($this->id($id));
        if ($lead === null) {
            $json ? $this->json(['error' => 'This enquiry no longer exists. Refresh the page.'], 404) : Response::abort(404);
        }
        $leadId = (int) $lead['id'];

        $stage = $this->request->input('stage', '');
        $stages = Enq::stages();
        if (!is_string($stage) || !isset($stages[$stage])) {
            $this->fail($json, $leadId, 'Please choose a valid stage.');
        }

        $reason = null;
        if ($stage === 'lost') {
            $reason = $this->lostReason();
            if ($reason === null) {
                $this->fail($json, $leadId, 'Please choose why this lead was lost.', 'lost_reason');
            }
        }

        $from = (string) $lead['stage'];
        $changed = $from !== $stage || ($stage === 'lost' && $reason !== $lead['lost_reason']);
        $customerId = $lead['customer_id'] !== null ? (int) $lead['customer_id'] : null;

        if ($changed) {
            Enq::moveStage($leadId, $stage, $reason);

            // A won lead is a customer — link one automatically when the plan has the customer database.
            if ($stage === 'won' && $customerId === null && License::has('customers')) {
                try {
                    $customerId = Enq::ensureCustomer($lead);
                } catch (\Throwable $e) {
                    error_log('[lead won → customer] ' . $e->getMessage());
                }
            }

            $body = sprintf('Moved from %s to %s', Enq::stageLabel($from), Enq::stageLabel($stage))
                . ($stage === 'lost' && $reason ? ' — reason: ' . $reason : '');
            LeadActivityRepository::log($leadId, $customerId, 'stage', $body);
            Activity::log('updated', 'enquiry', $leadId, sprintf('Moved lead “%s” to %s', $lead['name'], Enq::stageLabel($stage)));
        }

        $bookingUrl = null;
        if ($stage === 'won' && $customerId !== null && feature('bookings') && can('bookings')) {
            $bookingUrl = url('/bookings/create?' . http_build_query(['customer_id' => $customerId, 'enquiry_id' => $leadId]));
        }
        $message = $changed ? sprintf('%s moved to %s.', $lead['name'], Enq::stageLabel($stage)) : 'No change.';

        if ($json) {
            $this->json([
                'ok' => true,
                'id' => $leadId,
                'stage' => $stage,
                'label' => Enq::stageLabel($stage),
                'status' => Enq::statusForStage($stage),
                'message' => $message,
                'booking_url' => $bookingUrl,
                'enquiry_url' => url('/enquiries/' . $leadId),
            ]);
        }
        Flash::success($message . ($bookingUrl ? ' Next step: create the booking from the enquiry page.' : ''));
        $back = $this->request->input('back', '');
        $this->redirect(is_string($back) && $back === 'enquiry' ? '/enquiries/' . $leadId : '/leads');
    }

    /** Reason from the config list (+ optional detail), or null. */
    private function lostReason(): ?string
    {
        $reason = $this->request->input('lost_reason', '');
        $detail = $this->request->input('lost_note', '');
        if (!is_string($reason) || !in_array($reason, config('crm')['lost_reasons'] ?? [], true)) {
            return null;
        }
        $detail = is_string($detail) ? trim((string) preg_replace('/\s+/u', ' ', strip_tags($detail))) : '';
        return mb_substr($reason . ($detail !== '' ? ' — ' . $detail : ''), 0, 200);
    }

    private function fail(bool $json, int $leadId, string $message, string $field = 'stage'): never
    {
        if ($json) {
            $this->json(['error' => $message, 'field' => $field], 422);
        }
        Flash::error($message . ' You can do it from the enquiry page.');
        $this->redirect('/enquiries/' . $leadId);
    }
}
