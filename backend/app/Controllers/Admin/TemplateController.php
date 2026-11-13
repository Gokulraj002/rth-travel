<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Template;
use App\Core\Validator;
use App\Repositories\TemplateRepository;

/** WhatsApp & email message templates with {{placeholders}} (feature "templates", permission "templates"). */
final class TemplateController extends Controller
{
    public function index(): never
    {
        $q = mb_substr((string) $this->request->query('q', ''), 0, 100);
        $grouped = array_fill_keys(array_keys(TemplateRepository::CHANNELS), []);
        foreach (TemplateRepository::all($q) as $t) {
            $grouped[(string) $t['channel']][] = $t;
        }
        $this->view('admin/templates/index', [
            'title' => 'Message templates',
            'pretitle' => 'Ready-made WhatsApp & email messages',
            'grouped' => $grouped,
            'q' => $q,
            'pageActions' => '<a href="' . e(url('/templates/create')) . '" class="btn btn-primary">' . icon('plus') . ' New template</a>',
        ]);
    }

    public function create(): never
    {
        $channel = (string) $this->request->query('channel', 'whatsapp');
        $this->form(null, isset(TemplateRepository::CHANNELS[$channel]) ? $channel : 'whatsapp');
    }

    public function store(): never
    {
        [$data, $errors] = $this->validate(null);
        if ($errors !== []) {
            $this->back('/templates/create', $errors);
        }
        $id = TemplateRepository::create($data);
        Activity::log('created', 'template', $id, sprintf('Created %s template “%s”', TemplateRepository::CHANNELS[$data['channel']], $data['name']));
        $this->unknownPlaceholderNotice($data);
        Flash::success(sprintf('Template “%s” saved.', $data['name']));
        $this->redirect('/templates');
    }

    public function edit(string $id): never
    {
        $tpl = $this->findOr404($id);
        $this->form($tpl, (string) $tpl['channel']);
    }

    public function update(string $id): never
    {
        $tpl = $this->findOr404($id);
        $tid = (int) $tpl['id'];
        [$data, $errors] = $this->validate($tid);
        if ($errors !== []) {
            $this->back('/templates/' . $tid . '/edit', $errors);
        }
        TemplateRepository::update($tid, $data);
        Activity::log('updated', 'template', $tid, sprintf('Updated template “%s”', $data['name']));

        // Warn (not block) when a name other modules look up was changed.
        $oldName = (string) $tpl['name'];
        $oldChannel = (string) $tpl['channel'];
        if (TemplateRepository::isSystem($oldName, $oldChannel) && ($data['name'] !== $oldName || $data['channel'] !== $oldChannel)
            && !TemplateRepository::nameTaken($oldName, $oldChannel)) {
            Flash::info(sprintf(
                'Heads-up: “%s” is used for %s. Until a %s template has that exact name again, the panel will use its built-in default text.',
                $oldName, TemplateRepository::SYSTEM[$oldName][1], TemplateRepository::CHANNELS[$oldChannel],
            ));
        }
        $this->unknownPlaceholderNotice($data);
        Flash::success('Template saved.');
        $this->redirect('/templates');
    }

    public function toggle(string $id): never
    {
        $tpl = $this->findOr404($id);
        $active = (int) $tpl['is_active'] !== 1;
        TemplateRepository::setActive((int) $tpl['id'], $active);
        Activity::log('updated', 'template', (int) $tpl['id'], sprintf('%s template “%s”', $active ? 'Switched on' : 'Switched off', $tpl['name']));
        if (!$active && TemplateRepository::isSystem((string) $tpl['name'], (string) $tpl['channel'])) {
            Flash::info(sprintf('“%s” is switched off — the panel will use its built-in default text for %s.', $tpl['name'], TemplateRepository::SYSTEM[$tpl['name']][1]));
        }
        Flash::success(sprintf('“%s” is now %s.', $tpl['name'], $active ? 'on' : 'off'));
        $this->redirect('/templates');
    }

    public function duplicate(string $id): never
    {
        $tpl = $this->findOr404($id);
        $newId = TemplateRepository::duplicate($tpl);
        Activity::log('created', 'template', $newId, sprintf('Duplicated template “%s”', $tpl['name']));
        Flash::success('Copy created (switched off). Edit it, then switch it on.');
        $this->redirect('/templates/' . $newId . '/edit');
    }

    public function destroy(string $id): never
    {
        $tpl = $this->findOr404($id);
        TemplateRepository::delete((int) $tpl['id']);
        Activity::log('deleted', 'template', (int) $tpl['id'], sprintf('Deleted template “%s”', $tpl['name']));
        if (TemplateRepository::isSystem((string) $tpl['name'], (string) $tpl['channel'])
            && !TemplateRepository::nameTaken((string) $tpl['name'], (string) $tpl['channel'])) {
            Flash::info(sprintf('“%s” was used for %s — the panel will now use its built-in default text.', $tpl['name'], TemplateRepository::SYSTEM[$tpl['name']][1]));
        }
        Flash::success(sprintf('Template “%s” deleted.', $tpl['name']));
        $this->redirect('/templates');
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @param array<string,mixed>|null $tpl */
    private function form(?array $tpl, string $channel): never
    {
        $placeholders = Template::placeholders();
        $this->view('admin/templates/form', [
            'title' => $tpl ? 'Edit template' : 'New template',
            'pageTitle' => $tpl ? (string) $tpl['name'] : 'New message template',
            'pretitle' => 'Message templates',
            'tpl' => $tpl,
            'channel' => $channel,
            'placeholders' => $placeholders,
            'samples' => $this->samples(),
        ]);
    }

    /**
     * @return array{0:array{channel:string,name:string,subject:?string,body:string,is_active:int},1:array<string,string>}
     */
    private function validate(?int $exceptId): array
    {
        $input = [
            'channel' => $this->request->input('channel', ''),
            'name' => $this->request->input('name', ''),
            'subject' => $this->request->input('subject', ''),
            'body' => $this->request->input('body', ''),
            'is_active' => $this->request->input('is_active', '0'),
        ];
        $v = Validator::make($input, [
            'channel' => 'required|in:' . implode(',', array_keys(TemplateRepository::CHANNELS)),
            'name' => 'required|string|max:120',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|max:5000',
            'is_active' => 'bool',
        ], ['body' => 'Message', 'is_active' => 'Active']);
        $errors = $v->errors();
        $data = $v->validated();

        if (!isset($errors['channel'])) {
            if ($data['channel'] === 'email' && ($data['subject'] ?? null) === null && !isset($errors['subject'])) {
                $errors['subject'] = 'An email needs a subject line.';
            }
            if (!isset($errors['name']) && TemplateRepository::nameTaken((string) $data['name'], (string) $data['channel'], $exceptId)) {
                $errors['name'] = sprintf('Another %s template already has this name.', TemplateRepository::CHANNELS[$data['channel']]);
            }
        }
        if ($errors !== []) {
            return [[], $errors];
        }
        return [[
            'channel' => (string) $data['channel'],
            'name' => (string) $data['name'],
            'subject' => $data['channel'] === 'email' ? (string) $data['subject'] : null,
            'body' => str_replace("\r\n", "\n", (string) $data['body']),
            'is_active' => (int) $data['is_active'],
        ], []];
    }

    /** @param array{subject:?string,body:string} $data */
    private function unknownPlaceholderNotice(array $data): void
    {
        $unknown = TemplateRepository::unknownPlaceholders(($data['subject'] ?? '') . ' ' . $data['body'], array_keys(Template::placeholders()));
        if ($unknown !== []) {
            Flash::info('These placeholders are not recognised and will be left blank: {{' . implode('}}, {{', $unknown) . '}}');
        }
    }

    /** Example values for the live preview. @return array<string,string> */
    private function samples(): array
    {
        $company = Template::companyVars();
        return [
            'customer_name' => 'Asha Sharma',
            'trip' => 'Kashmir Paradise — 5 Nights / 6 Days',
            'amount' => inr2(48500),
            'quotation_number' => 'QT-' . date('Y') . '-0012',
            'quotation_link' => rtrim((string) Env::get('APP_URL', ''), '/') . url('/q/a1b2c3d4e5f6'),
            'valid_until' => date('j M Y', strtotime('+7 days')),
            'booking_number' => 'BK-' . date('Y') . '-0007',
            'travel_start' => date('j M Y', strtotime('+30 days')),
            'travel_end' => date('j M Y', strtotime('+35 days')),
            'paid' => inr2(20000),
            'balance' => inr2(28500),
            'payment_amount' => inr2(20000),
            'receipt_no' => 'RC-' . date('Y') . '-0021',
            'company_name' => (string) ($company['company_name'] ?: 'Reyaan Trip Holiday'),
            'company_phone' => (string) ($company['company_phone'] ?: '+91 60060 45211'),
            'company_email' => (string) $company['company_email'],
            'company_website' => (string) $company['company_website'],
        ];
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $tpl = TemplateRepository::find($this->id($rawId));
        if ($tpl === null) {
            Response::abort(404);
        }
        return $tpl;
    }
}
